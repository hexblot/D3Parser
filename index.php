<?php

/* Sample usage */
$config = array( 'lang' => 'en' );  // Override any config option
$conn = D3Parser::getInstance($config);
print '<pre>';
print_r($conn->getTag('Hexblot-2294'));
print_r($conn->getHero('Hexblot'));