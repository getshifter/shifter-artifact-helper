list:
	find . -name "*.php" > files

pkg: clean
	tar -cvzf shifter-artifact-helper.tgz -T files

clean:
	rm -f shifter-artifact-helper.tgz
	rm -rf integration_test/volume/app/mu-plugins/*

.PHONY: list pkg clean
