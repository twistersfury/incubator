#!/usr/bin/env bash

git clone -q https://github.com/phalcon/cphalcon.git
git checkout origin/1.2.4
git pull origin 1.2.4
cd cphalcon/build
sudo ./install && phpenv config-add ../../tests/phalcon.ini
