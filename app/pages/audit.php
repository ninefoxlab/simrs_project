<?php
require_permission($pdo,(int)$user['id_user'],'reporting.view');
$cols=table_columns($pdo,'audit_trail');
$rows=table_rows($pdo,'audit_trail',100);
?>
<div class="page-head"><div><h1>Audit Trail</h1><p>Riwayat aktivitas sistem. Menampilkan hingga 100 data terbaru.</p></div></div>
<section class="card"><div class="table-wrap"><table><thead><tr><?php foreach(array_slice($cols,0,8) as $c): ?><th><?= e($c['Field']) ?></th><?php endforeach; ?></tr></thead><tbody><?php if(!$rows): ?><tr><td class="empty">Belum ada catatan audit.</td></tr><?php endif; ?><?php foreach($rows as $r): ?><tr><?php foreach(array_slice($cols,0,8) as $c): ?><td><?= e($r[$c['Field']]??'—') ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></section>
