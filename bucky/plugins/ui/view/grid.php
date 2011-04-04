<?php
$config = UAdmin_Grids::getDownloadsGridConfig();
$data = $this->gridData();
$s = $data['state'];
#var_dump($data['state']);
#echo $data['query'];
?>

<form method="get" action="<?php echo $this->gridUrl() ?>"><fieldset>

<input type="text" name="search[_quick]" value="<?php echo !empty($s['search']['_quick']) ? $this->q($s['search']['_quick']) : '' ?>">
<input type="submit" value="Search"/>

<label>Rows per page:</label>
<select onchange="location.href=this.value">
<?php foreach ($config['pageSizeOptions'] as $i): ?>
    <option value="<?php echo $this->gridUrl(array('pageSize'=>$i)) ?>" <?php if (!empty($s['pageSize']) && $s['pageSize']==$i):?>selected="selected"<?php endif ?> ><?php echo $i ?></option>
<?php endforeach ?>
</select>

<label>Page:</label>
<?php if ($s['page']>1): ?><a href="<?php echo $this->gridUrl(array('page'=>$s['page']-1)) ?>">&lt;&lt;</a><?php endif ?>
<select onchange="location.href=this.value">
<?php for ($i=1; $i<=$s['totalPages']; $i++): ?>
    <option value="<?php echo $this->gridUrl(array('page'=>$i)) ?>" <?php if (!empty($s['page']) && $s['page']==$i):?>selected="selected"<?php endif ?> ><?php echo $i ?></option><?php endfor ?>
</select>
<?php if ($s['page']<$s['totalPages']): ?><a href="<?php echo $this->gridUrl(array('page'=>$s['page']+1)) ?>">&gt;&gt;</a><?php endif ?>
 of <?php echo $s['totalPages'] ?> ::

<?php /*if ($s['page']>6): ?><a href="<?php echo $this->gridUrl(array('page'=>1)) ?>">1</a> <?php endif ?>
<?php if ($s['page']>7): ?> ... <?php endif ?>
<?php for ($i=max($s['page']-5, 1); $i<=min($s['page']+5, $s['totalPages']); $i++): ?>
<a href="<?php echo $this->gridUrl(array('page'=>$i)) ?>" <?php if ($i==$s['page']): ?>class="active"<?php endif ?> ><?php echo $i ?></a>
<?php endfor ?>
<?php if ($s['page']<=$s['totalPages']-7): ?> ... <?php endif ?>
<?php if ($s['page']<=$s['totalPages']-6): ?> <a href="<?php echo $this->gridUrl(array('page'=>$s['totalPages'])) ?>"><?php echo $s['totalPages'] ?></a><?php endif */ ?>

<?php echo 'Rows '.$s['fromRow'].' - '.$s['toRow'].' of '.$s['totalRows'] ?>

<table class="grid">
<thead>
    <tr>
<?php foreach ($config['columns'] as $colId=>$column): ?>
        <td>
            <a href="<?php echo $this->sortUrl($colId) ?>" class="<?php echo !empty($s['sort']) && $s['sort']==$colId ? 'sort-'.$s['sortDir'] : '' ?>"><?php echo $this->q($column['title']) ?></a>
        </td>
<?php endforeach ?>
    </tr>
<?php if (!empty($config['filters'])): ?>
    <tr>
<?php foreach ($config['columns'] as $colId=>$column): ?>
        <td>
<?php if (!empty($config['filters'][$colId])): $filter = $config['filters'][$colId]; ?>

<?php endif ?>
        </td>
<?php endforeach ?>
    </tr>
<?php endif ?>
</thead>
<tbody>
<?php foreach ($data['rows'] as $rowId=>$row): ?>
    <tr class="<?php echo $rowId%2 ? 'odd' : 'even' ?>">
<?php foreach ($config['columns'] as $colId=>$column): $cell = $row[$colId]; ?>
        <td>
<?php switch (!empty($column['type']) ? $column['type'] : ''): ?>
<?php case 'link': ?> <a href="<?php echo $cell['href'] ?>"><?php echo $this->cellData($cell) ?></a><?php break; ?>
<?php default: echo $this->cellData($cell) ?>
<?php endswitch ?>
        </td>
<?php endforeach ?>
    </tr>
<?php endforeach ?>
</tbody>
</table>

</fieldset></form>