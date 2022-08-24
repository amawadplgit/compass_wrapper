<?php 
print substr(str_shuffle(str_repeat($x='abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXZ', ceil(16/strlen($x)) )),1,16);