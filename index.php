<?php

include "tpb.php";
try {
    $tpb_test = new TPB_parser();
    
    //set id to new torrent page
    $tpb_test->setId(9178618);
    
    //get info array
    $info = $tpb_test->torrentInfo();
    
    //switch to another page
    $tpb_test->setId(9179793);
    
    //get comments
    $comments = $tpb_test->getComments();
    
    
} catch (\Exception $exc) {
    //see what went wrong
    echo $exc->getMessage();
}
?>
