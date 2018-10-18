list:
	find . -type d -name integration_test -prune -o -type f -name '*.php' -print > files

pkg: clean
	tar -cvzf shifter-artifact-helper.tgz -T files

clean:
	rm -f shifter-artifact-helper.tgz
	rm -rf integration_test/volume/app/mu-plugins/*

.PHONY: list pkg clean
