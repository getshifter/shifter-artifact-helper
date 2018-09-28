list:
	find . -name "*.php" > files

pkg:
	rm -f shifter-artifact-helper.tgz
	tar -cvzf shifter-artifact-helper.tgz -T files

.PHONY: list pkg
