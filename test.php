<?php
$pass = 'testpass';
$pdf = 'test.pdf';
$enc = 'test_enc.pdf';
file_put_contents($pdf, "Dummy PDF content");
exec("qpdf --encrypt $pass $pass 256 -- $pdf $enc 2>&1", $out, $ret);
var_dump($out, $ret);
