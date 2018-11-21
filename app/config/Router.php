<?php
use AIS\libraries\Routes;

Routes::add('admin/taliffsss','admin/Mark::work');

Routes::add('admin','admin/Mark::index');

Routes::add('admin/must-try','admin/Mark::test');

Routes::add('default','Main::mark');

?>