list:
	find . -type d \( -name integration_test -o -name tests \) -prune -o -type f -name '*.php' -print > files

test:
	./tests/run.sh

pkg: clean
	tar -cvzf shifter-artifact-helper.tgz -T files

prepare: pkg
	mkdir -p integration_test/volume/shifter-artifact-helper
	tar xvzf shifter-artifact-helper.tgz -C integration_test/volume/shifter-artifact-helper

clean:
	rm -f shifter-artifact-helper.tgz
	rm -rf integration_test/volume/shifter-artifact-helper

.PHONY: list pkg clean prepare test
