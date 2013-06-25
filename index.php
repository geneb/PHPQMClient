<?php
include "PHPQMClient.php";

# An example page that uses PHPQMClient.php, it also acts as a test diagnosis program.
# Some things, like setQMPHPErrorOutput you dont need For a live program. But help debugging.
# Set for your own system
$port = '4243';
$address = 'pick.tesson.co.uk';
$user = "qmtest";
$pass = "123456";
$account = "QMTEST";

# Turns on error reporting, and sets what style of output
setQMPHPErrorPutput(QMPHP_ECHOERRORS);
# Set to what ever you want to end a line to be,
# eg \n for console, or </br> for html
$eol = "\n";
#$eol = "</br>";

qmconnect($address, $port, $user, $pass, $account);

# Tests
$file = qmopen("TESTDATA");
$readtest = qmread($file, "read.test");
echo "ReadTest \t($readtest)$eol";
echo "Write test \t".qmwrite($file, "write.test", date("H:i D F")).$eol;
echo "WriteU Test \t".qmwriteu($file, "writeu.test", date("H:i D F")).$eol;
# Note php doesnt care what case the sub is.
echo "Execute \t".QMExecute("SORT TESTDATA").$eol;
echo "Select  \t".qmselect($file, 1).$eol;
echo "Readnext \t".qmreadnext(1).$eol;
echo "Readlist \t".qmreadlist(1).$eol;
echo "Clearselect \t".qmclearselect(1).$eol;
// Broken
//echo "Select index ".qmselectindex($file, "1", "key", "read.test").$eol;
//echo qmgeterror()."\n";
//echo "\t\tfirst item".qmreadnext("1").$eol;
//echo qmgeterror()."\n";

echo "Writing lock test file ".$eol;
if( qmread($file, "lock.test") == NULL ) {
  echo "Clause ".qmlastclause().$eol;
}

echo "Lock ".qmlockrecord($file, QMLOCK_CREATE, "lock.test").$eol;
echo "RecordLocked Test ".qmrecordlocked($file, "lock.test").$eol;
echo "FileLock ".qmfilelock($file, FALSE).$eol;
echo "FileUnlock ".qmfileunlock($file).$eol;
# Note php doesnt care what case the sub is.
echo "Delete \t".QMDelete($file, "write.test").$eol;
echo "DeleteU \t".QMDeleteU($file, "writeu.test").$eol;
echo "Release: \t".qmrelease($file,"read.test").$eol;
qmclose($file);


//echo qmgeterror()."\n";
QMDisconnect();
?>
