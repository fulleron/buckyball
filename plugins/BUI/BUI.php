<?php
/**
* Copyright 2011 Unirgy LLC
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*/

class BUI
{
    static public function bootstrap()
    {
        BLayout::i()->setViewRootDir('view');
    }

    public function jqgridData($orm)
    {
        $p = BRequest::i()->request();
        $data = $orm->paginate(array('p'=>$p['page'], 'ps'=>$p['rows'], 's'=>$p['sidx'], 'sd'=>$p['sord']));
        $res = $data['state'];
        $res['rows'] = BDb::many_as_array($data['rows']);
        return $res;
    }
}

class BViewGrid extends BView
{
    public function gridUrl($changeRequest=array())
    {
        $grid = $this->grid;
        $grid['request'] = BUtil::arrayMerge($grid['request'], $changeRequest);
        return BApp::href($grid['config']['gridUrl']).'?'.http_build_query($grid['request']);
    }

    public function sortUrl($colId)
    {
        if (!empty($this->grid['request']['sort']) && $this->grid['request']['sort']==$colId) {
            $change = array('sortDir'=>$this->grid['request']['sortDir']=='desc'?'asc':'desc');
        } else {
            $change = array('sort'=>$colId, 'sortDir'=>'asc');
        }
        return $this->gridUrl($change);
    }

    public function cellData($cell, $rowId=null, $colId=null)
    {
        /*
        if (empty($this->grid['data']['rows'][$rowId][$colId])) {
            return '';
        }
        $cell = $this->grid['data']['rows'][$rowId][$colId];
        */
        if (!empty($this->grid['config']['columns'][$colId]['render'])) {
            $render = $this->grid['config']['columns'][$colId]['render'];
            if (is_callable($render)) {
                return call_user_func($render, $cell, $rowId, $colId);
            }
        }

        return nl2br($this->q(!empty($cell['value']) ? $cell['value'] : ''));
    }

    public function gridPrepareConfig($c)
    {
        if (empty($c['pageSizeOptions'])) {
            $c['pageSizeOptions'] = array(25,50,100);
        }
        if (empty($c['pageSize'])) {
            $c['pageSize'] = $c['pageSizeOptions'][0];
        }
        if (empty($c['search'])) {
            $c['search'] = new stdClass;
        }
        if (!isset($c['sort'])) {
            $c['sort'] = '';
        }
        if (!isset($c['sortDir'])) {
            $c['sortDir'] = 'asc';
        }
        if (empty($c['fields'])) {
            $c['fields'] = $c['columns'];
            foreach ($c['columns'] as $cId=>$col) {
                $c['columns'][$cId]['fields'] = array($cId);
            }
        }
        foreach ($c['columns'] as $cId=>$col) {
            if (!empty($col['fields'])) {
                foreach ($col['fields'] as $fId) {
                    $c['fields'][$fId]['col'] = $cId;
                }
            }
        }
        BEvents::i()->fire('BViewGrid::gridPrepareConfig:after', array('config'=>&$c));
        return $c;
    }

    public function gridData(array $options=array())
    {
        // fetch grid configuration
        $grid = $this->grid;
        $config =& $grid['config'];
        if (!empty($grid['serverConfig'])) {
            $config = BUtil::arrayMerge($config, $grid['serverConfig']);
        }

        $config = $this->gridPrepareConfig($config);

        // fetch request parameters
        if (empty($grid['request'])) {
            $grid['request'] = BRequest::i()->get();
        }
        $p = BRequest::i()->sanitize($grid['request'], array(
            'page' => array('int', !empty($config['page']) ? $config['page'] : 1),
            'pageSize' => array('int', !empty($config['pageSize']) ? $config['pageSize'] : $config['pageSizeOptions'][0]),
            'sort' => array('lower', !empty($config['sort']) ? $config['sort'] : null),
            'sortDir' => array('alnum|lower', !empty($config['sortDir']) ? $config['sortDir'] : 'asc'),
            'search' => array('', array()),
        ));

        foreach ($p['search'] as $k=>$s) {
            if ($s==='') {
                unset($p['search'][$k]);
            }
        }

        BDb::connect();
        // create collection factory
        #$orm = AModel::factory($config['model']);
        $table = $config['table'];
        $orm = BORM::for_table($table);
        if (!empty($config['table_alias'])) {
            $orm->table_alias($config['table_alias']);
            $tableAlias = $config['table_alias'];
        } else {
            $tableAlias = $config['table'];
        }

        if (!empty($config['where'])) {
            $orm->where_complex($config['where']);
        }

        BEvents::i()->fire('BViewGrid::gridData:initORM: '.$config['id'], array('orm'=>$orm, 'grid'=>$grid));

        $mapColumns = array();

        $this->_processGridJoins($config, $mapColumns, $orm, 'before_count');
        $this->_processGridFilters($config, $p['search'], $orm);

        // fetch count of all rows and calculate resulting state variables
        $countOrm = clone $orm;
        $p['totalRows'] = $countOrm->count();
        $p['pageSize'] = min($p['pageSize'], 10000);
        $p['totalPages'] = ceil($p['totalRows']/$p['pageSize']);
        $p['page'] = min($p['page'], $p['totalPages']);
        $p['fromRow'] = $p['page'] ? $p['pageSize']*($p['page']-1)+1 : 1;
        $p['toRow'] = min($p['fromRow']+$p['pageSize']-1, $p['totalRows']);

        $this->_processGridJoins($config, $mapColumns, $orm, 'after_count');

        // add columns to select
        foreach ($config['select'] as $k=>$f) {
            if ($f[0]==='(') {
                $orm->select_expr(str_replace('{t}', $tableAlias, $f), $k);
                continue;
            }
            $orm->select((strpos($f, '.')===false ? $tableAlias.'.' : '').$f, !is_int($k) ? $k : null);
        }

        // add sorting
        if ($p['sort']) {
            if (!empty($config['columns'][$p['sort']]['sort_by'])) {
                $p['sort'] = $config['columns'][$p['sort']]['sort_by'];
            }
            $orderBy = $p['sortDir']=='desc' ? 'order_by_desc' : 'order_by_asc';
            $orm->$orderBy($p['sort']);
        }
#var_dump($orm);
        // run query
        try {
            $models = $orm->offset($p['fromRow']-1)->limit($p['pageSize'])->find_many();
        } catch (PDOException $e) {
            echo $e->getMessage()."\n<hr>\n".$orm->get_last_query()."\n";
            exit;
        }
#echo $orm->get_last_query();

        // init result
        $p['description'] = $this->stateDescription($p);
        $grid['result'] = array('state'=>$p, 'raw'=>array(), 'out'=>array()/*, 'query'=>ORM::get_last_query()*/);

        foreach ($models as $i=>$model) {
            $r = $model->as_array();
            if (empty($options['no_raw'])) {
                $grid['result']['raw'][$i] = $r;
            }
            if (empty($options['no_out'])) {
                foreach ($config['fields'] as $k=>$f) {
                    $field = !empty($f['field']) ? $f['field'] : $k;
                    $grid['result']['out'][$i][$k]['raw'] = isset($r[$field]) ? $r[$field] : null;
                    $value = isset($r[$field]) ? $r[$field] : (isset($f['default']) ? $f['default'] : '');
                    if (!empty($f['options'][$value])) $value = $f['options'][$value];
                    if (!empty($f['format'])) {
                        $value = $this->_formatGridValue($f['format'], $value);
                    }
                    $grid['result']['out'][$i][$k]['value'] = $value;
                    if (!empty($f['href'])) {
                        $grid['result']['out'][$i][$k]['href'] = BUtil::injectVars($f['href'], $r);
                    }
                }
                if (!empty($config['map'])) {
                    foreach ($config['map'] as $m) {
                        $value = $r[$m['value']];
                        if (!empty($m['format'])) {
                            $value = $this->_formatGridValue($m['format'], $value);
                        }
                        $grid['result']['out'][$i][$m['field']][$m['prop']] = $value;
                    }
                }
            }
        }
        BEvents::i()->fire('BGridView::gridData:after: '.$config['id'], array('grid'=>&$grid));

        $this->grid = $grid;
        return $grid;
    }

    protected function _formatGridValue($format, $value)
    {
        if (is_string($format)) {
            switch ($format) {
                case 'boolean': $value = !!$value; break;
                case 'date': $value = $value ? BLocale::i()->datetimeDbToLocal($value) : ''; break;
                case 'currency': $value = $value ? '$'.number_format($value, 2) : ''; break;
            }
        } elseif (is_callable($format)) {
            $value = $format($value);
        }
        return $value;
    }

    protected function _processGridJoins(&$config, &$mapColumns, $orm, $when='before_count')
    {
        if (empty($config['join'])) {
            return;
        }
        $mainTableAlias = !empty($config['table_alias']) ? $config['table_alias'] : $config['table'];
        foreach ($config['join'] as $j) {
            if (empty($j['when'])) {
                $j['when'] = 'before_count';
            }
            if ($j['when']!=$when) {
                continue;
            }

            $table = (!empty($j['db']) ? $j['db'].'.' : '').$j['table'];
            $tableAlias = isset($j['alias']) ? $j['alias'] : $j['table'];

            $localKey = isset($j['lk']) ? $j['lk'] : 'id';
            $foreignKey = isset($j['fk']) ? $j['fk'] : 'id';

            $localKey = (strpos($localKey, '.')===false ? $mainTableAlias.'.' : '').$localKey;
            $foreignKey = (strpos($foreignKey, '.')===false ? $tableAlias.'.' : '').$foreignKey;

            $op = isset($j['op']) ? $j['op'] : '=';


            $joinMethod = (isset($j['type']) ? $j['type'].'_' : '').'join';

            $where = isset($j['where']) ? str_replace(array('{lk}', '{fk}', '{lt}', '{ft}'), array($localKey, $foreignKey, $mainTableAlias, $tableAlias), $j['where']) : array($foreignKey, $op, $localKey);

            $orm->$joinMethod($table, $where, $tableAlias);

            /*
            if (!empty($j['select'])) {
                list($localTable, ) = explode('.', $localKey);
                foreach ($j['select'] as $jm) {
                    $fieldAlias = !empty($jm['alias']) ? $jm['alias'] : null;
                    if (isset($jm['field'])) {
                        $orm->select($tableAlias.'.'.$jm['field'], $fieldAlias);
                    } elseif (isset($jm['expr'])) {
                        $expr = str_replace(array('{lt}', '{ft}'), array($localTable, $tableAlias), $jm['expr']);
                        $orm->select_expr($expr, $fieldAlias);
                    }
                }
            }

            if (!empty($j['where'])) {
                $orm->where_raw($j['where'][0], $j['where'][1]);
            }
            */
        }
    }

    protected function _processGridFilters(&$config, $filters, $orm)
    {
        if (empty($config['filters'])) {
            return;
        }
        foreach ($config['filters'] as $fId=>$f) {
            $f['field'] = !empty($f['field']) ? $f['field'] : $fId;

            if ($fId=='_quick') {
                if (!empty($f['expr']) && !empty($f['args']) && !empty($filters[$fId])) {
                    $args = array();
                    foreach ($f['args'] as $a) {
                        $args[] = str_replace('?', $filters['_quick'], $a);
                    }
                    $orm->where_raw('('.$config['filters']['_quick']['expr'].')', $args);
                }
                continue;
            }
            if (!empty($f['type'])) switch ($f['type']) {
            case 'text':
                if (!empty($filters[$fId])) {
                    $this->_processGridFiltersOne($f, 'like', $filters[$fId].'%', $orm);
                }
                break;

            case 'text-range': case 'number-range': case 'date-range':
                if (!empty($filters[$fId]['from'])) {
                    $this->_processGridFiltersOne($f, 'gte', $filters[$fId]['from'], $orm);
                }
                if (!empty($filters[$fId]['to'])) {
                    $this->_processGridFiltersOne($f, 'lte', $filters[$fId]['to'], $orm);
                }
                break;

            case 'select':
                if (!empty($filters[$fId])) {
                    $this->_processGridFiltersOne($f, 'equal', $filters[$fId], $orm);
                }
                break;

            case 'multiselect':
                if (!empty($filters[$fId])) {
                    $filters[$fId] = explode(',', $filters[$fId]);
                    $this->_processGridFiltersOne($f, 'in', $filters[$fId], $orm);
                }
                break;
            }
        }
    }

    protected function _processGridFiltersOne($filter, $op, $value, $orm)
    {
        if (!empty($filter['raw'][$op])) {
            $orm->where_raw($filter['raw'][$op], $value);
        } else {
            $method = 'where_'.$op;
            $orm->$method($filter['field'], $value);
        }
    }

    public function stateDescription($params)
    {
        $descrArr = array();
        if (!empty($params['search'])) {
            $descr = "Filtered by: ";
            foreach ($params['search'] as $k=>$s) {
                if ($k==='_quick') {
                    $filter = array('type'=>'quick');
                    $descr .= '<b>Quick search</b>';
                } else {
                    $filter = $this->grid['config']['filters'][$k];
                    $descr .= '<b>'.$filter['label'].'</b>';
                }
                switch ($filter['type']) {
                case 'multiselect':
                    $opts = array();
                    $os = explode(',', $s);
                    if (sizeof($os)==1) {
                        $descr .= ' is <u>'.$filter['options'][$os[0]].'</u>';
                    } else {
                        foreach ($os as $o) {
                            $opts[] = $filter['options'][$o];
                        }
                        $descr .= ' is one of <u>'.join(', ', $opts).'</u>';
                    }
                    break;

                case 'text-range': case 'date-range':
                    $descr .= ' is between <u>'.BResponse::q($s['from']).'</u> and <u>'.BResponse::q($s['to']).'</u>';
                    break;

                case 'quick':
                    $descr .= ' by <u>'.BResponse::q($s).'</u>';
                    break;

                default:
                    $descr .= ' contains <u>'.BResponse::q($s).'</u>';
                }
                $descr .= '; ';
            }
            $descrArr[] = $descr;
        }
        return $descrArr ? join("; ", $descrArr) : '';
    }
}

class BViewJqGrid extends BViewGrid
{
    public function jqGridConfig(array $o = array())
    {
        $c = $this->gridPrepareConfig($this->grid['config']);
        $colNames = array();
        $colModel = array();
        foreach ($c['columns'] as $k=>$col) {
            $colNames[] = $col['title'];
            $col['name'] = $col['field'];
            $col['index'] = !empty($col['sort_by']) ? $col['sort_by'] : $col['field'];
            unset($col['field'], $col['sort_by'], $col['title']);
            $colModel[] = $col;
        }
        $result = $o + array(
            'url' => BApp::href($c['dataUrl']),
            'datatype' => 'json',
            'colNames' => $colNames,
            'colModel' => $colModel,
            'rowNum' => $c['pageSize'],
            'rowList' => $c['pageSizeOptions'],
            'pager' => '#'.$c['id'].'_pager',
            'sortname' => $c['sort'],
            'sortorder' => $c['sortDir'],
        );
        return $result;
    }

    public function jqGridData(array $o = array())
    {
        $r = BRequest::i()->get();
        $this->grid['request'] = array(
            'page' => !empty($r['page']) ? $r['page'] : null,
            'pageSize' => !empty($r['rows']) ? $r['rows'] : null,
            'sort' => !empty($r['sidx']) ? $r['sidx'] : null,
            'sortDir' => !empty($r['sord']) ? $r['sord'] : null,
            'search' => array('', array()),
        );
        $this->gridData(array('no_raw'=>true));
        $data = array();
        foreach ($this->result['out'] as $rowId=>$row) {
            foreach ($row as $colId=>$cell) {
                $data[$rowId][$colId] = $cell['value'];
            }
        }
        return $data;
    }
}