#!/bin/sh



ps aux | grep "php bin/hyperf.php start" | awk '{print $2}' | xargs kill -9
