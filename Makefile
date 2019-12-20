.PHONY: docker-shfmt
docker-shfmt:
	docker build -t docker-shfmt -f Dockerfile-shfmt . --quiet

.PHONY: shfmt
shfmt: docker-shfmt
	docker run --rm -v "$$(pwd):/src" -w /src local-shfmt bash -c "shfmt -s -ln posix -i 0 -kp -w install-php-extensions"
	docker run --rm -v "$$(pwd):/src" -w /src local-shfmt bash -c "shfmt -s -ln posix -i 0 -kp -w scripts/common"
	docker run --rm -v "$$(pwd):/src" -w /src local-shfmt bash -c "shfmt -s -ln posix -i 0 -kp -w scripts/travisci-test-extensions"
	docker run --rm -v "$$(pwd):/src" -w /src local-shfmt bash -c "shfmt -s -ln posix -i 0 -kp -w scripts/travisci-update-readme"
	docker run --rm -v "$$(pwd):/src" -w /src local-shfmt bash -c "shfmt -s -ln posix -i 0 -kp -w scripts/update-readme"
