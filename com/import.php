<?php

class BImport extends BClass
{
    protected $fields = array();
    protected $dir = 'shared';
    protected $model = '';

    public function getFieldData()
    {
        BPubSub::i()->fire(__METHOD__, array('fields'=>&$this->fields));
        return $this->fields;
    }

    public function getFieldOptions()
    {
        $options = array();
        foreach ($this->getFieldData() as $f=>$_) {
            $options[$f] = $f;
        }
        return $options;
    }

    public function getImportDir()
    {
        return FCom_Core::i()->dir('storage/import/'.$this->dir);
    }

    public function updateFieldsDueToInfo($info)
    {
        //use in child classes
    }

    public function getFileInfo($file)
    {
        // assume we know nothing about the file
        $info = array();
        // open file for reading
        $fp = fopen($file, 'r');
        // get first line in the file
        $r = fgets($fp);
        fclose($fp);
        $row = array();
        foreach (array("\t", ',', ';', '|') as $chr) {
            $row = str_getcsv($r, $chr);
            if (sizeof($row)>1) {
                $info['delim'] = $chr;
                break;
            }
        }
        // if delimiter not known, can't parse the file
        if (empty($info['delim'])) {
            return false;
        }
        // save first row data
        $info['first_row'] = $row;
        // find likely column names
        $this->updateFieldsDueToInfo($info);
        $fields = $this->getFieldData();
        foreach ($row as $i=>$v) {
            foreach ($fields as $f=>$fd) {
                if (!empty($fd['pattern']) && empty($fd['used'])
                    && preg_match("#{$fd['pattern']}#i", $v)
                ) {
                    $info['columns'][$i] = $f;
                    $fields[$f]['used'] = true;
                    break;
                }
            }
        }
        // if no column names found, do not skip first row
        $info['skip_first'] = !empty($info['columns']);
        return $info;
    }

    /**
     * @param null|false|array $config
     * @param bool $update
     * @return array|bool|mixed
     */
    public function config($config=null, $update=false)
    {
        $dir = FCom_Core::i()->dir('storage/run/'.$this->dir);
        $file = BSession::i()->sessionId().'.json';
        $filename = $dir.'/'.$file;
        if ($config) { // create config lock
            if ($update) {
                $old = $this->config();
                $config = array_replace_recursive($old, $config);
            }
            if (empty($config['status'])) {
                $config['status'] = 'idle';
            }
            file_put_contents($filename, BUtil::toJson($config));
            return true;
        } elseif ($config===false) { // remove config lock
            unlink($filename);
            return true;
        } elseif (!file_exists($filename)) { // no config
            return false;
        } else { // config exists
            $contents = file_get_contents($filename);
            $config = BUtil::fromJson($contents);
            return $config;
        }
    }

    public function run()
    {
        #BSession::i()->close();
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        ob_implicit_flush();
        //gc_enable();
        BDb::connect();

        //disable debug
        $oldDebugMode = BDebug::i()->mode();
        BDebug::i()->mode('DISABLED');

        $timer = microtime(true);

        if (empty($this->model)) {
            throw new BException("Model is required");
        }

        $modelClass = $this->model;
        $model = $modelClass::i();

        if (!method_exists($model, 'import')) {
            throw new BException("Model should implement import method");
        }
        $config = $this->config();
        $filename = $this->getImportDir().'/'.$config['filename'];
        $status = array(
            'start_time' => time(),
            'status' => 'running',
            'rows_total' => sizeof(file($filename)), // file() will load entire file in memory, may be not good idea???
            'rows_processed' => 0,
            'rows_skipped' => 0,
            'rows_warning' => 0,
            'rows_error' => 0,
            'rows_nochange' => 0,
            'rows_created' => 0,
            'rows_updated' => 0,
            'memory_usage' => memory_get_usage(),
            'run_time' => 0,
            'errors' => ''
        );
        $this->config($status, true);
        $fp = fopen($filename, 'r');
        if (!empty($config['skip_first'])) {
            for ($i=0; $i<$config['skip_first']; $i++) {
                fgets($fp);
                $status['rows_skipped']++;
            }
        }

        $importConfig = array();

        $statusUpdate = 50;
        if ($config['batch_size']) {
            $statusUpdate = $config['batch_size'];
        }
        if ($config['multivalue_separator']) {
            $importConfig['format']['multivalue_separator'] = $config['multivalue_separator'];
        }
        if ($config['nesting_separator']) {
            $importConfig['format']['nesting_separator'] = $config['nesting_separator'];
        }

        $dataBatch = array();
        while (($r = fgetcsv($fp, 0, $config['delim']))) {
            if (count($r) != count($config['columns']) ) {
                continue;
            }
            $row = array_combine($config['columns'], $r);
            foreach ($config['defaults'] as $k=>$v) {
                if (!is_null($v) && $v!=='' && (!isset($row[$k]) || $row[$k]==='')) {
                    $row[$k] = $v;
                }
            }

            $data = array();
            foreach ($row as $k=>$v) {
                $f = explode('.', $k);
                if (empty($f[0]) || empty($f[1])) {
                    continue;
                }
                $data[$f[0]][$f[1]] = $v;
            }


            if ($config['batch_size'] && !empty($data)) {
                $dataBatch[] = array_pop($data);
                if (count($dataBatch) % $config['batch_size'] === 0) {
                    $resultBatch = $model->import($dataBatch, $importConfig);
                    if (!empty($resultBatch['errors'])) {
                        $status['errors'] = $resultBatch['errors'];
                    }
                    foreach($resultBatch as $result) {
                        if (isset($status['rows_'.$result['status']])) {
                            $status['rows_'.$result['status']]++;
                        }
                    }
                    $dataBatch = array();
                }
            } else {
                $result = $model->import($data, $importConfig);
                if (!empty($result['errors'])) {
                    $status['errors'] = $result['errors'];
                }
                if (isset($status['rows_'.$result['status']])) {
                    $status['rows_'.$result['status']]++;
                }
            }

            //$result = array('status'=>'skipped');


            if (++$status['rows_processed'] % $statusUpdate === 0) {
                //gc_collect_cycles();
                $update = $this->config();
                if (!$update || $update['status']!=='running' || $update['start_time']!==$status['start_time']) {
                    return false;
                }
                $status['memory_usage'] = memory_get_usage();
                $status['run_time'] = microtime(true)-$timer;
                $this->config($status, true);
            }
        }
        fclose($fp);

        //upload last data
        if ($config['batch_size'] && !empty($dataBatch)) {
            $resultBatch = $model->import($dataBatch, $importConfig);
            if (!empty($resultBatch['errors'])) {
                $status['errors'] = $resultBatch['errors'];
            }
            foreach($resultBatch as $result) {
                if (isset($status['rows_'.$result['status']])) {
                    $status['rows_'.$result['status']]++;
                }
            }
            $status['memory_usage'] = memory_get_usage();
            $status['run_time'] = microtime(true)-$timer;
            $this->config($status, true);
        }

        $status['memory_usage'] = memory_get_usage();
        $status['run_time'] = microtime(true)-$timer;
        $status['status'] = 'done';
        $status['rows_processed'] = $status['rows_total'];
        $this->config($status, true);

        BDebug::i()->mode($oldDebugMode);
        return true;
    }
}