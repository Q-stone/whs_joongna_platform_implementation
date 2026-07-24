<?php
/* pages/_tags.php - 공용 태그 목록 */
global $sql;
$TAGS = [];
foreach ($sql->run("SELECT tag_id,tag_name FROM tags ORDER BY tag_id") as $t) {
    $TAGS[(int)$t['tag_id']] = $t['tag_name'];
}