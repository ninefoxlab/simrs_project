<?php
function table_columns(PDO $pdo, string $table): array {
    static $cache=[];
    if (isset($cache[$table])) return $cache[$table];
    if (!preg_match('/^[a-z0-9_]+$/i',$table)) throw new RuntimeException('Nama tabel tidak valid.');
    $stmt=$pdo->query("SHOW COLUMNS FROM `{$table}`");
    return $cache[$table]=$stmt->fetchAll(PDO::FETCH_ASSOC);
}
function table_primary(PDO $pdo,string $table): ?string {
    $primary=[];
    foreach(table_columns($pdo,$table) as $c) if(($c['Key']??'')==='PRI') $primary[]=$c['Field'];
    // Jangan mengedit tabel dengan primary key gabungan melalui editor generik.
    return count($primary)===1 ? $primary[0] : null;
}
function table_foreign_keys(PDO $pdo,string $table): array {
    $s=$pdo->prepare("SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL");
    $s->execute([$table]); $out=[];
    foreach($s->fetchAll() as $r) $out[$r['COLUMN_NAME']]=$r;
    return $out;
}
function table_label_column(PDO $pdo,string $table): string {
    $cols=table_columns($pdo,$table); $names=array_column($cols,'Field');
    foreach(['nama','nama_tampilan','nama_pasien','nama_barang','nama_obat','nama_unit','kode','kode_role','username','no_rm','nomor_rm','no_kunjungan'] as $n) if(in_array($n,$names,true)) return $n;
    return table_primary($pdo,$table) ?? $names[0];
}
function table_rows(PDO $pdo,string $table,int $limit=20): array {
    $pk=table_primary($pdo,$table);
    $sql="SELECT * FROM `{$table}`";
    if($pk) $sql.=" ORDER BY `{$pk}` DESC";
    $sql.=' LIMIT '.max(1,min(100,$limit));
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
function foreign_options(PDO $pdo,array $fk): array {
    $table=$fk['REFERENCED_TABLE_NAME']; $column=$fk['REFERENCED_COLUMN_NAME'];
    if(!preg_match('/^[a-z0-9_]+$/i',$table)||!preg_match('/^[a-z0-9_]+$/i',$column)) return [];
    $label=table_label_column($pdo,$table);
    $stmt=$pdo->query("SELECT `{$column}` AS v, `{$label}` AS label FROM `{$table}` ORDER BY `{$label}` LIMIT 200");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
