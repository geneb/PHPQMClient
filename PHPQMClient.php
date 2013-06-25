<?php
# PHP API library for OpenQM - 0.5.32 Alpha release
# Copyright (C) 2009 Diccon Tesson - Adrian Tesson Associates

# This library is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.

# This library is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
# Lesser General Public License for more details.

# You should have received a copy of the GNU Lesser General Public
# License along with this library; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
# ___________________________________________________________________

# Overview
# -------
# PHPQMLib allows you to connect to the QM Client API interface and issue 
# various commands and requests.

# Help / Discussion / updates
# ---------------------------
# Go to http://gpl.openqm.com/wiki/PHP_Native_API for help, Discusion and updates.
# Failing that Google/Search for "GPL OpenQM" to find where the community went.

# How to use this library
# -----------------------
# Place this file in the same directory as the file that intends to use it,
# or place it in the library path for your PHP configuration.
#
# In your php code Enter the following line at the top 
#   include "PHPQMLib.php"

# The first you need to call the connect function, something like this;
#   qmconnect("my.qm.server.com", "4243", "testuser", "testpass", "QMTESTACCOUNT");
# or 
#   qmconnect("192.168.1.1", "4243", "testuser", "testpass", "QMTESTACC");

# You can now use the variety of PHPQM functions like qmread(), 
# qmdelete(), etc as you wish.
# When you are done, for tidyness issue a;
#   qmdisconnect();
# command, tidlying closing the socket.
# Each page/script will need to (re)connect. Connection persistance and pooling is 
# a bit bigger than this simple lib for now.

# Check out qmLastClause() QMPHPClient function to cater for commands
# hitting ELSE, LOCKED, etc conditions

# Generally you can follow the QMClient docs in terms of server functionality.
# Using the below defines() as what is and is not implimented.
# Better PHP specific docs will be written later, when we go Beta

# Dev notes
# ---------
# For packet constructions and definitions see the QM API docs or read the 
# Sourcecode REM notes in GPL.BP VBSRVR in the OpenQM server source code.

# TODO
# 1. every command should check if connected.
# 2. Make sure every function returns FALSE or NULL for failure
# 3. Arrange readQMPacket to have a timeout, to allow dev/user to cleanly catch a timeout
# 4. I've screwed up sucess true/false returns, check all of these in call functions

# Code
# ---------
# Variables
# ---------
# Holds the open Socket connection
$QMSocket = NULL;
# Holds the last recieved packet
$QMLastReplyPacket = NULL;

# Client to server header size
define("CTSHEADLEN", 6);
# QM Command codes
define("QMCOMMAND_QUIT",         1);
define("QMCOMMAND_GETERROR",     2);
define("QMCOMMAND_ACCOUNT",      3);
define("QMCOMMAND_OPEN",         4);
define("QMCOMMAND_CLOSE",        5);
define("QMCOMMAND_READ",         6);
define("QMCOMMAND_READL",        7); //**? Need work for Conditional execution. Not fully tested.
define("QMCOMMAND_READLW",       8); //**? "
define("QMCOMMAND_READU",        9); //**? "
define("QMCOMMAND_READUW",      10); //**? "
define("QMCOMMAND_SELECT",      11);
define("QMCOMMAND_READNEXT",    12);
define("QMCOMMAND_CLEARSELECT", 13);
define("QMCOMMAND_READLIST",    14);
define("QMCOMMAND_RELEASE",     15);
define("QMCOMMAND_WRITE",       16);
define("QMCOMMAND_WRITEU",      17);
define("QMCOMMAND_DELETE",      18);
define("QMCOMMAND_DELETEU",     19);
#define("QMCOMMAND_CALL",        20); //** Complex one to program, leave til last.
define("QMCOMMAND_EXECUTE",     21);
define("QMCOMMAND_RESPOND",     22);
define("QMCOMMAND_LOGIN",       24);
# 25 QM Login local 		- Not implemented
define("QMCOMMAND_SELECTINDEX", 26);
# 27 Enter licenced package 	- Not Implimented
# 28 Exit  licenced package   	- Not Implimented
# 29 Open QMNet tbc
define("QMCOMMAND_LOCKRECORD",  30);  //Broke, causes !PICK to premote a keyboard input request?! Or not, darnit
define("QMCOMMAND_CLEARFILE",   31);  // untested
define("QMCOMMAND_FILELOCK",    32);
define("QMCOMMAND_FILEUNLOCK",  33);
define("QMCOMMAND_RECORDLOCKED",34);

define("DEBUG", true);

# QM Status and conditional Codes
define("QMSTATUS_OK",        0); // Action successful
define("QMSTATUS_ON.ERROR",  1); // Action took ON ERROR clause
define("QMSTATUS_ELSE",      2); // Action took ELSE clause
define("QMSTATUS_ERROR",     3); // Action failed. Error text available
define("QMSTATUS_LOCKED",    4); // Took locked clause

# User callable Functions
#----------
# QM Quit
function qmquit() {
  sendQMPacket(QMCOMMAND_QUIT,"");
  readQMPacket(); // Reads sucess
}

# QM Get Error
# Retrieves the last cached error message from the server
function qmgeterror() {
  sendQMPacket(QMCOMMAND_GETERROR, "");
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);  //If error, report it
  return $reply["msg"]; // Returns reply regardless (bad?)
}

# Set account this con is working on (should be set at least once)
function qmaccount($account) {
  sendQMPacket(QMCOMMAND_ACCOUNT, $account);
  $reply = readQMPacket();
  
  if($reply["err"] != 0) {
    $ans = false;
    phpQMError($reply);
  } else {
    $ans = true;
  }

  return $ans;
}

# Open QM File for use, returns the File number for later use.
function qmopen($filename) {

  sendQMPacket(QMCOMMAND_OPEN,$filename);

  $reply = readQMPacket();

  if ($reply["err"] == 0) {
    $msg = $reply["msg"];
    if ($msg != NULL || $msg != "") {
      $bdown = unpack("vfileno",$reply["msg"]);
      $fileno = $bdown["fileno"];
    }
  }else {
    phpQMError($reply);
    $fileno = NULL;
  }

  return $fileno;
}

# QM Close file. Closes an open file reference
function qmclose($file) {
  sendQMPacket(QMCOMMAND_CLOSE, $file);
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);  //If error, report it
}

# Read record from open QM file
# Returns the record, or NULL if failed
function qmread($file, $itemkey) {
  $record = NULL;
  
  sendQMPacket(QMCOMMAND_READ, pack("v",$file).$itemkey);
  
  $reply = readQMPacket();
  if ($reply["err"] == 0) {
    $record = $reply["msg"];
  }
  else {
    phpQMError($reply);
  }

  return $record;
}

# QM ReadL, read file locking it so others cant access it
# See qmLastClause() for way to handle LOCKED clause etc
function qmreadl($file, $itemkey) {
  $record = NULL;
  
  sendQMPacket(QMCOMMAND_READL, pack("v",$file).$itemkey);
  
  $reply = readQMPacket();
  if ($reply["err"] == 0) {
    $record = $reply["msg"];
  }
  else {
    phpQMError($reply);
  }

  return $record;
}

# QM ReadLW shared locked waiting. Read file with lock, waiting if locked.
# ** Need to check how this would work. May need to put a wait loop round 
#** the readQMPacket. Or the socket read might holt up the process for me, 
#**if the server doesnt respond. Needs testing to figure all this out.
function qmreadlw($file, $itemkey) {
  $record = NULL;
  
  sendQMPacket(QMCOMMAND_READLW, pack("v",$file).$itemkey);
  
  $reply = readQMPacket();
  if ($reply["err"] == 0) {
    $record = $reply["msg"];
  }
  else {
    phpQMError($reply);
  }

  return $record;
}

# QM ReadU
# See qmLastClause() for way to handle LOCKED clause etc
function qmreadu($file, $itemkey) {
  $record = NULL;
  
  sendQMPacket(QMCOMMAND_READU, pack("v",$file).$itemkey);
  
  $reply = readQMPacket();
  if ($reply["err"] == 0) {
    $record = $reply["msg"];
  }
  else {
    phpQMError($reply);
  }

  return $record;
}

# QM ReadUW
# ** Need to check how this would work. May need to put a wait loop round
# ** the readQMPacket. Or the socket read might holt up the process for me
# ** if the server doesnt respond. Needs testing to figure all this out.
function qmreaduw($file, $itemkey) {
  $record = NULL;
  
  sendQMPacket(QMCOMMAND_READUW, pack("v",$file).$itemkey);
  
  $reply = readQMPacket();
  if ($reply["err"] == 0) {
    $record = $reply["msg"];
  }
  else {
    phpQMError($reply);
  }

  return $record;
}

# QM Select
# Select a whole file to the list number provided.
# Returns true on sucess, false on failure.
function qmselect($file, $list){
  $sucess = TRUE;
  sendQMPacket(QMCOMMAND_SELECT, pack("vv",$file,$list));
  $reply = readQMPacket();
  if ($reply["err"] != 0) {
    phpQMError($reply);
    $sucess = FALSE;
  }
  return $sucess;
}

# QM READNEXT
# read next key from given select list.
# Returns the key, or NULL if failure.
function qmreadnext($listno) {
  $replyKey = NULL;
  sendQMPacket(QMCOMMAND_READNEXT, $listno);
  $reply = readQMPacket();

  # Check response
  if ($reply["err"] == 0) {
    $replyKey = $reply["msg"];
  }
  else { 
    $replyKey = NULL;
    phpQMError($reply);
  }

  return $replyKey;
}

# QM Clear Select
# Clears the given select list
function qmclearselect($listno) {
  $sucess = FALSE;
  sendQMPacket(QMCOMMAND_CLEARSELECT, $listno);
  $reply = readQMPacket();

  # Check response
  if ($reply["err"] == 0) {
    $sucess = TRUE;
  }
  else { 
    $sucess = FALSE;
    phpQMError($reply);
  }

  return $sucess;
}

# QM Read list
# Read active select list into a dynamic array
function qmreadlist($listno){

  sendQMPacket(QMCOMMAND_READLIST, $listno);
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);

  # Msg block is the AM delimited string. "" if error.
  return $reply["msg"];
}

# QM Release
# Release item lock for a given file and item
function qmrelease($file, $itemid) {
  $sucess = TRUE;

  $file = pack("v", $file); // Make into Short Int

  sendQMPacket(QMCOMMAND_RELEASE, $file.$itemid);
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# QM Write
# Write a record to an open file
function qmwrite($file, $itemid, $record) {
  $sucess = TRUE;

  # Packet format
  #      fileno (short integer)
  #      id_len (short integer)
  #      id
  #      data
  $idLen = strlen($itemid);
  $block = pack("vva*a*", $file, $idLen, $itemid, $record);

  sendQMPacket(QMCOMMAND_WRITE, $block);
  $reply = readQMPacket();

  # Catch Error
  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# QM Write U
# Write a record to an open file, maintaining item lock
function qmwriteu($file, $itemid, $record) {
  $sucess = TRUE;

  # Packet format
  #      fileno (short integer)
  #      id_len (short integer)
  #      id
  #      data
  $idLen = strlen($itemid);
  $block = pack("vva*a*", $file, $idLen, $itemid, $record);

  sendQMPacket(QMCOMMAND_WRITEU, $block);
  $reply = readQMPacket();

  # Catch Error
  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# Delete record from open file
# In:  fileno (short integer)
#      id
function qmdelete($file, $itemid) {
  sendQMPacket(QMCOMMAND_DELETE, pack("va*",$file,$itemid));
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);
}

# QM Delete U
# Delete record from open file, Maintianing item lock
function qmdeleteu($file, $itemid) {
  $sucess = TRUE;

  # In:  fileno (short integer)
  #      id
  sendQMPacket(QMCOMMAND_DELETEU, pack("va*",$file,$itemid));
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# QM Call
# 

# Execute Command in TCL on server
# Returns string of commands output.
# In:  Command string
# Out: Command output
function qmexecute($command) {
  $output = "";
  sendQMPacket(QMCOMMAND_EXECUTE, $command);
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);
  return $reply["msg"]; 
}

# QM Repond
# Respond to an INPUT request/condition from the called program or command
#
# QMCLient docs claim this should never happen/be called. Read QMClient docs about this.
# Its in reference to conditional execution. This is yet to be 
# fully investigated for correct use in this PHP Library.
# function is here for completness
function qmrespond($command) {
  sendQMPacket(QMCOMMAND_RESPOND, $command);
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);
  return $reply["msg"];
}

# QM End command
# End Command
#
# QMCLient docs claim this should never happen/be called. Read QMClient docs about this.
# Its in reference to conditional execution. This is yet to be 
# fully investigated for correct use in this PHP Library.
# function is here for completness
function qmendcommand($command) {
  sendQMPacket(QMCOMMAND_ENDCOMMAND, $command);
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);
}

#QM Select Index
function qmselectindex($file, $listno, $index, $searchValue) {
  $sucess = TRUE;
  # Pre Contruct packet data elements/format
  //$file = pack("v",$file); // Unsure about this, it should already be a short, but wouldnt this make sure? or screw it up, no idea -DAT 
  $listno = pack("v", $listno);
  $indexLen = pack("v",strlen($index));
  $searchValueLen = pack("v", strlen($searchValue));

  # Send packet
  sendQMPacket(QMCOMMAND_SELECTINDEX, $file.$listno
				     .$indexLen.$index
				     .$searchValueLen.$searchValue);
//echo $file.$listno.$indexLen.$index.$searchValueLen.$searchValue;

  # Check sucess
  $reply = readQMPacket();
  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# Flags for qmlockrecord
define("QMLOCK_CREATE",   0);
define("QMLOCK_UPDATE",   1);
define("QMLOCK_CREATENW", 2);
define("QMLOCK_UPDATENW", 3);

# QM Lock Record
# Lock or update a lock for a record
# Locktype is a flag to indicate what kind of Pick lock command to use underneath
# QMLOCK_CREATE - Equivilant of RECORDLOCKL in QM Basic
# QMLOCK_CREATENW - Same but doesnt wait if its locked
# QMLOCK_UPDATE - Equivilant of RECORDLOCKU in QM Basic
# QMLOCK_UPDATENW - Same as above, but doesnt wait
function qmlockrecord($file, $locktype, $item){
  $sucess = TRUE;

  # Filter and validate locktype
  if (!is_numeric($locktype) || ($locktype < 0 || $locktype > 3))
    $locktype = 0;

  # Convert to short int
  $locktype = pack("v", $locktype);

  # Send command 
  sendQMPacket(QMCOMMAND_LOCKRECORD,$file.$locktype.$item);
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# QM ClearFile
# Clears a QM File of all records, deleting them all
function qmclearfile($file){
  $sucess = TRUE;
  
  sendQMPacket(QMCOMMAND_CLEARFILE, $file);
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = FALSE;

  return $sucess;
}

# QM Filelock
# Gets lock for entire given file
# $file - file opened by qmopen, $wait - Boolean if the command should wait if it cant get lock straight away
function qmfilelock($file, $wait) {
  $sucess = NULL;

  # Check file is in right format.
  $file = pack("v", $file);

  # Convert wait into short
  if ($wait)
    $wait = pack("v",1);
  else
    $wait = pack("v",0);

  sendQMPacket(QMCOMMAND_FILELOCK, $file.$wait);
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = TRUE;
 
  return $sucess;
}

# QM File unlock
# Release an active lock on a whole file
function qmfileunlock($file) {
  $sucess = NULL;

  # Check file is in right format.
  $file = pack("v", $file);

  sendQMPacket(QMCOMMAND_FILEUNLOCK, $file);
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = TRUE;

  return $sucess;
}

# QM RecordLocked
# Checks if a given record in a given open file is locked
function qmrecordlocked($file, $item) {
  $result = NULL;

  # Check file is in right format.
  $file = pack("v", $file);

  sendQMPacket(QMCOMMAND_RECORDLOCKED, $file.$item);
  $reply = readQMPacket();

  # If error state
  if ($reply["err"] != 0) phpQMError($reply);
  else { 
    // Pick up server result
    $result = $reply["msg"];
  }

  return $result;
}

# Rest of Commands
# x
function dummy() {
  $sucess = NULL;

#  sendQMPacket(QMCOMMAND_, "");
  $reply = readQMPacket();

  if ($reply["err"] != 0) phpQMError($reply);
  else $sucess = TRUE;

  return $sucess;
}

# QMPHPClient Utility routines.
#------------------------------

# Connect to QMServer on given address and port.
# logging in using the supplied username password and account.
function qmconnect($address, $port, $user, $pass, $account) {
  global $qmSocket;

  # if port is blank, use default
  if( $port == "" ) 
    $port = "4243";

  # Enable all socket error reporting
  error_reporting(E_ALL);

  /* Create a TCP/IP socket. */
  phpQMStatus("Creating Socket... ");
  $qmSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
  if ($qmSocket === false) {
      phpQMStatus("socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n");
  } else {
      phpQMStatus("OK.\n");
  }

  phpQMStatus("Attempting to connect to '$address' on port '$port'...");
  $result = socket_connect($qmSocket, $address, $port);
  if ($result === false) {
      phpQMStatus("socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($qmSocket)) . "\n");
  } else {
      phpQMStatus("OK.\n");
  }

  phpQMStatus("Prodding server..... ");
  socket_send($qmSocket, "", 0,0); //kick server
  $ack = socket_read($qmSocket, 1024);
  if($ack == pack("c",6) ) {
    phpQMStatus("Server Acknowledged\n");
  }

  # login/Authenticate user
  $loginerror = qmlogin($user, $pass);
  
  # Set account
  if($loginerror == 0)
    qmaccount($account);
}

# Close down the active QM Connection
function qmdisconnect() {
  global $qmSocket;
  socket_close($qmSocket);
}

# QMLastClause
# Returns the Conditional opcode for the last command
# EG THEN, or LOCKED
# Can be used after a command to determin what action to take
function qmLastClause() {
  global $QMLastReplyPacket;
  return $QMLastReplyPacket["status"];
}

# eg of qmLastClause usage
#  switch ( qmLastClause() ) 
#  {
#    case QMSTATUS_LOCKED:
#      echo "Record Locked\n";
#      break;
#    case QMSTATUS_ELSE:
#      echo "No record of that name found\n";
#      break;
#  }
#


# Internal routines
# -----------------

# Send login username and password command for authentication.
function qmlogin($user, $pass) {
  $error = 0;

  phpQMStatus("Sending username and pass... ");
  
  $user_len = strlen($user);
  $pass_len = strlen($pass);
  # Pad both user and pass to even no of bytes, QM requirement
  if((strlen($user) % 2) == 1) {
    $user = $user.pack("x");
    #$user_len++;
  }
  if((strlen($pass) % 2) == 1) {
    $pass = $pass.pack("x");
    #$pass_len++;
  }

  #  userLen (2b) | user (?b) | passLen (2b) | pass (?b) 
  $block = pack("va*",$user_len,$user).pack("va*",$pass_len,$pass);

  sendQMPacket(QMCOMMAND_LOGIN, $block);

  $reply = readQMPacket();

  if ($reply["err"] == 0) {
    phpQMStatus("Logged in\n");
  } else {
    $error = $reply["err"];
    phpQMStatus("Login failed\n");
    phpQMError($reply);
  }

  return $error;
}

# Send a QM API packet to the open socket.
function sendQMPacket($command, $msgBlock) {
  global $qmSocket;
  global $QMLastReplyPacket;

  # Check socket is active.
  # TODO

  # Calculate message block size
  $msgLen = strlen($msgBlock);

  # 4B packet len | 2B command | msg block  
  $packet = pack("Vva*",CTSHEADLEN + $msgLen,$command,$msgBlock);
  
  # Send Packet
  socket_send($qmSocket, $packet, strlen($packet), 0);

  # Clear Stored last packet, unless its an GetErr command
  if($command != QMCOMMAND_GETERROR)
    $QMLastReplyPacket = NULL;

}

# Reads QM packtfrom open socket. 
# Returns a hashed arrays with ("len", "err", "status", "msg") keys
# e.g. $reply = readQMPacket();
# echo $reply["msg"];
function readQMPacket() {
  global $qmSocket;
  global $QMLastReplyPacket;

  # Read QM's reply
  $reply = socket_read($qmSocket, 1024);

  # Unscramble the binary packet into a name hashed array
  $replyPacket = unpack("Vlen/verr/Vstatus/A*msg",$reply);

  # Record reply, for conditional Clause utility
  # Only if it's been cleared
  if ($QMLastReplyPacket == NULL)
    $QMLastReplyPacket = $replyPacket;

  return $replyPacket;
}

# Set how the lib displays errors
define("QMPHP_ECHOERRORS",   1);
define("QMPHP_SILENTERRORS", 2);
define("QMPHP_LOGERRORS",    3);
define("QMPHP_HTMLERRORS",    4);

$QMPHPErrorOutput = QMPHP_LOGERRORS;
# Can define the type of Status output, use the above Codes
function setQMPHPErrorPutput($code) {
  $QMPHPErrorOutput = $code;
}

# Report current status/failure/sucess
# Single point of use for centralised redirectable debugging
function phpQMStatus($msg) {
  global $QMPHPErrorOutput;

  switch ($QMPHPErrorOutput) {
    case QMPHP_ECHOERRORS:
      echo $msg."\n";
      break;
    case QMPHP_HTMLERRORS:
      echo $msg."</br>";
      break;
    case QMPHP_SILENTERRORS:
      break;
    case QMPHP_LOGERRORS:
      break;
  }
}

# Report an error, 
# Single point of recording all functions should use this to allow
# redirection of debugging and errors
function phpQMError($packet) {
  if (DEBUG)
    echo "QMError ".$packet["status"]." ".$packet["msg"]."\n";
}
?>
