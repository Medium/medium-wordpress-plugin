#!/bin/sh

all:
	@echo "make zip - Create medium.zip"

zip:
	rm medium.zip
	zip -rT medium.zip ./*


