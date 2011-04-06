<?php

class BuckyUI
{
    static public function init()
    {
        BLayout::i()->viewRootDir('ui/view');
    }
}

class BViewGrid extends BView
{
    public function gridUrl($changeRequest=array())
    {
        $grid = $this->grid;
        $grid['request'] = BParser::i()->arrayMerge($grid['request'], $changeRequest);
        return BApp::baseUrl().$grid['config']['gridUrl'].'?'.http_build_query($grid['request']);
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
        return $this->q(isset($cell['title']) ? $cell['title'] : (!empty($cell['value']) ? $cell['value'] : ''));
    }

    public function rowActions($row)
    {
        return array();
    }

    public function gridData()
    {
        $parser = BParser::i();
        // fetch grid configuration
        $config = $this->grid['config'];
        if (!empty($this->grid['server_config'])) {
            $config = array_merge_recursive($config, $this->grid['server_config']);
        }

        // fetch request parameters
        if (empty($this->grid['request'])) {
            $grid = $this->grid;
            $grid['request'] = BRequest::i()->get();
            $this->grid = $grid;
        }
        $p = BRequest::i()->sanitize($this->grid['request'], array(
            'page' => array('int', !empty($config['page']) ? $config['page'] : 1),
            'pageSize' => array('int', !empty($config['pageSize']) ? $config['pageSize'] : $config['pageSizeOptions'][0]),
            'sort' => array('alnum|lower', !empty($config['sort']) ? $config['sort'] : null),
            'sortDir' => array('alnum|lower', !empty($config['sortDir']) ? $config['sortDir'] : 'asc'),
            'search' => array('', array()),
        ));

        // create collection factory
        #$orm = AModel::factory($config['model']);
        $orm = ORM::for_table($config['main_table']);

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
        foreach ($config['columns'] as $colName=>$col) {
            if (empty($col['virtual'])) {
                if (isset($col['expr'])) {
                    $orm->select_expr($col['expr'], $colName);
                } else {
                    $orm->select($config['main_table'].'.'.(isset($col['field']) ? $col['field'] : $colName));
                }
            }
            if (!empty($col['map'])) {
                foreach ($col['map'] as $propName=>$expr) {
                    $mapAlias = '_alias_'.$colName.'_'.$propName;
                    $mapColumns[$colName][$propName] = $mapAlias;
                    if (is_array($expr)) {
                        $orm->select_expr($expr[0], $mapAlias);
                    } else {
                        $orm->select($config['main_table'].'.'.$expr, $mapAlias);
                    }
                }
            }
        }

        // add sorting
        if ($p['sort']) {
            $orderBy = $p['sortDir']=='desc' ? 'order_by_desc' : 'order_by_asc';
            $orm->$orderBy($p['sort']);
        }
#var_dump($orm);
        // run query
        $models = $orm->offset($p['fromRow']-1)->limit($p['pageSize'])->find_many();
#echo $orm->get_last_query();

        // init result
        $result = array('state' => $p, 'rows' => array(), 'query'=>ORM::get_last_query());

        // format rows
        foreach ($models as $model) {
            $r = $model->as_array();

            foreach ($config['columns'] as $k=>$col) {
                $a = array('value'=>isset($r[$k]) ? $r[$k] : null);
                if (!empty($mapColumns[$k])) {
                    foreach ($mapColumns[$k] as $propName=>$propField) {
                        if (!is_null($r[$propField])) $a[$propName] = $r[$propField];
                    }
                }
                if (!empty($col['type'])) {
                    switch ($col['type']) {
                        case 'row_checkbox': continue 2;
                        case 'link': $a['href'] = $parser->injectVars($col['href'], $r); break;
                        case 'actions': $a = call_user_func($col['callback'], $row); break;
                        default: if (!empty($this->grid['data_formatters'][$col['type']])) {
                            $fmt = $this->grid['data_formatters'][$col['type']];
                            $a = call_user_func($fmt['callback'], $r, $col);
                        } else {
                            #throw new Exception('Invalid column type: '.$col['type']);
                        }
                    }
                }
                $row[$k] = $a;
            }
            $result['rows'][] = $row;
        }

        $grid = $this->grid;
        $grid['data'] = $result;
        $this->grid = $grid;

        return $result;
    }

    protected function _processGridJoins(&$config, &$mapColumns, $orm, $when='before_acount')
    {
        if (empty($config['join'])) {
            return;
        }
        foreach ($config['join'] as $j) {
            if (empty($j['when'])) {
                $j['when'] = 'before_count';
            }
            if ($j['when']!=$when) {
                continue;
            }

            $table = (!empty($j['db']) ? $j['db'].'.' : '').$j['table'];
            $tableAlias = isset($j['alias']) ? $j['alias'] : '_join_'.$j['table'];

            $localKey = $j['lk'];
            $foreignKey = isset($j['fk']) ? $j['fk'] : $localKey;

            $localKey = (strpos($localKey, '.')===false ? $config['main_table'].'.' : '').$localKey;
            $foreignKey = (strpos($foreignKey, '.')===false ? $tableAlias.'.' : '').$foreignKey;

            $op = isset($j['op']) ? $j['op'] : '=';


            $joinMethod = (isset($j['type']) ? $j['type'].'_' : '').'join';

            $where = isset($j['where']) ? str_replace(array('{lk}', '{fk}'), array($localKey, $foreignKey), $j['where'])
                                        : array($foreignKey, $op, $localKey);

            $orm->$joinMethod($table, $where, $tableAlias);

            if (!empty($j['map'])) {
                foreach ($j['map'] as $jm) {
                    $fieldAlias = !empty($jm['alias']) ? $jm['alias'] : $tableAlias.'_'.$jm['col'].'_'.$jm['prop'];
                    if (isset($jm['col']) && isset($jm['prop'])) {
                        $mapColumns[$jm['col']][$jm['prop']] = $fieldAlias;
                    }

                    if (isset($jm['field'])) {
                        $orm->select($tableAlias.'.'.$jm['field'], $fieldAlias);
                    } elseif (isset($jm['expr'])) {
                        $expr = str_replace('{ft}', $tableAlias, $jm['expr']);
                        $orm->select_expr($expr, $fieldAlias);
                    }
                }
            }

            if (!empty($j['where'])) {
                $orm->where_raw($j['where'][0], $j['where'][1]);
            }
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
                if (!empty($filters[$fId.'_from'])) {
                    $this->_processGridFiltersOne($f, 'gte', $filters[$fId.'_from'], $orm);
                }
                if (!empty($filters[$fId.'_to'])) {
                    $this->_processGridFiltersOne($f, 'lte', $filters[$fId.'_to'], $orm);
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

}