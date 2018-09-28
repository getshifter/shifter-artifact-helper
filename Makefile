list:
	find . -name "*.php" > files

pkg:
	tar -cvzf shifter-artifact-helper.tgz -T files

.PHONY: list pkg
