# [1.11.0](https://github.com/ayaou/command-logger-bundle/compare/v1.10.0...v1.11.0) (2026-09-04)


### Features

* **command:** show the captured output in command-logger:show ([4bbfbc8](https://github.com/ayaou/command-logger-bundle/commit/4bbfbc8a5a3abf0ba02ff5ff6ad2af87e2260463)), closes [#20](https://github.com/ayaou/command-logger-bundle/issues/20)
* **output:** store what a watched command printed, behind an opt-in flag ([ae0b438](https://github.com/ayaou/command-logger-bundle/commit/ae0b438c89a31505b619915532d079dd850476a0))

# [1.10.0](https://github.com/ayaou/command-logger-bundle/compare/v1.9.0...v1.10.0) (2026-09-03)


### Bug Fixes

* **command:** filter command-logger:show through the shared filter ([7893757](https://github.com/ayaou/command-logger-bundle/commit/7893757d63d4ae2de5f160529d824131b0b0602d))
* **listener:** write log rows through DBAL, never through the unit of work ([929eb2d](https://github.com/ayaou/command-logger-bundle/commit/929eb2d42670dccf2527c19569782295f269f7cb))


### Features

* **config:** let the log live in a separate entity manager ([d6f8351](https://github.com/ayaou/command-logger-bundle/commit/d6f83511c2a60cf3664e7a803f9050bfc8f01eb8)), closes [#11](https://github.com/ayaou/command-logger-bundle/issues/11) [enqueue-dev#431](https://github.com/enqueue-dev/issues/431)

# [1.9.0](https://github.com/ayaou/command-logger-bundle/compare/v1.8.0...v1.9.0) (2026-09-03)


### Bug Fixes

* **listener:** never let a logging failure break the command ([989da57](https://github.com/ayaou/command-logger-bundle/commit/989da5705f56408deae7262f1d5c9ad94dee99e6))
* **quality:** clear the PHPStan level 8 findings ([b4e9dfe](https://github.com/ayaou/command-logger-bundle/commit/b4e9dfe0c7f4c2e821490fbd151b9092e119dfc3))


### Features

* **api:** expose command execution statistics over HTTP ([5f900dc](https://github.com/ayaou/command-logger-bundle/commit/5f900dc921654e8338920b06940263249c307afe))
* **stats:** report command execution statistics ([b6124b4](https://github.com/ayaou/command-logger-bundle/commit/b6124b4c4b8bf3f3520db10e8d132fcb61dbb788))

# [1.8.0](https://github.com/ayaou/command-logger-bundle/compare/v1.7.1...v1.8.0) (2026-09-03)


### Bug Fixes

* **api:** correct pagination metadata and error responses ([4fa125e](https://github.com/ayaou/command-logger-bundle/commit/4fa125e0e6f468925a3264c3aa26d5a16cb81489))


### Features

* **api:** make the REST API opt-in, disabled by default ([2b296d4](https://github.com/ayaou/command-logger-bundle/commit/2b296d40612db09b47dfc8bbd9a99423ef169728))
* **api:** make the REST API reachable and actually functional ([2ab7744](https://github.com/ayaou/command-logger-bundle/commit/2ab7744180360495f7c12bc03d2a1cb9ec3ca14d))

## [1.7.1](https://github.com/ayaou/command-logger-bundle/compare/v1.7.0...v1.7.1) (2026-09-02)


### Bug Fixes

* **listener:** detect the attribute on invokable commands ([e8dde6a](https://github.com/ayaou/command-logger-bundle/commit/e8dde6abb8878e4970d7de40684e8415f3ec12a3))

# [1.7.0](https://github.com/ayaou/command-logger-bundle/compare/v1.6.0...v1.7.0) (2026-09-02)


### Features

* **logging:** redact sensitive parameters and bound the error message ([176a59f](https://github.com/ayaou/command-logger-bundle/commit/176a59fa7438c7bd015ba93a4859208a70189d22))

# [1.6.0](https://github.com/ayaou/command-logger-bundle/compare/v1.5.1...v1.6.0) (2025-12-04)


### Features

* Support Symfony 8 and Doctrine 3 ([8bb3239](https://github.com/ayaou/command-logger-bundle/commit/8bb323924babb9067106c112a73855aae36355e4)), closes [#22](https://github.com/ayaou/command-logger-bundle/issues/22) [#23](https://github.com/ayaou/command-logger-bundle/issues/23)

## [1.5.1](https://github.com/ayaou/command-logger-bundle/compare/v1.5.0...v1.5.1) (2025-06-01)


### Bug Fixes

* Revert "fix: Capture new Symfony 7.3 command style" ([b183911](https://github.com/ayaou/command-logger-bundle/commit/b18391154f375ed6dfbb147dc700c4abc9dbf001))

# [1.5.0](https://github.com/ayaou/command-logger-bundle/compare/v1.4.0...v1.5.0) (2025-05-17)


### Bug Fixes

* Capture new Symfony 7.3 command style ([cee5d16](https://github.com/ayaou/command-logger-bundle/commit/cee5d1629e9e1ab72e35fd3ded3473529805165e))


### Features

* [#15](https://github.com/ayaou/command-logger-bundle/issues/15): Support wild cards on commands configuration ([692e249](https://github.com/ayaou/command-logger-bundle/commit/692e24958d42ea3a49c7943796e79b97f82507d2))
* New command to show logs ([3d242b3](https://github.com/ayaou/command-logger-bundle/commit/3d242b3ce38906af438f8230f2ffbb6af51e69d4))

# [1.4.0](https://github.com/ayaou/command-logger-bundle/compare/v1.3.0...v1.4.0) (2025-05-05)


### Features

* Support doctrine ORM 3 ([51e0b45](https://github.com/ayaou/command-logger-bundle/commit/51e0b45ede6d1bb9ebaa9a5180249202b9b282fb))

# [1.3.0](https://github.com/ayaou/command-logger-bundle/compare/v1.2.0...v1.3.0) (2025-03-29)


### Features

* Allow to log other commands via configuration ([60d2709](https://github.com/ayaou/command-logger-bundle/commit/60d2709925a07ca14dfb84f2c2370d4509d9f085))

# [1.2.0](https://github.com/ayaou/command-logger-bundle/compare/v1.1.0...v1.2.0) (2025-03-29)


### Features

* Add Symfony 7 support ([448ee2c](https://github.com/ayaou/command-logger-bundle/commit/448ee2ce1e36fc79836c4633337078daecf62c04))

# [1.1.0](https://github.com/ayaou/command-logger-bundle/compare/v1.0.0...v1.1.0) (2025-03-29)


### Features

* Lower php support to 8.1 & Update readme file ([06b16aa](https://github.com/ayaou/command-logger-bundle/commit/06b16aa0e7b0a9aa373cc8485b0fe82b3ddbaf2e))

# 1.0.0 (2025-03-28)


### Bug Fixes

* Adjust pipeline for semantic release ([c7ffc77](https://github.com/ayaou/command-logger-bundle/commit/c7ffc7757652452c8d4563fdaf96b440d8f17c5d))
* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
* Fix release job ([232b98c](https://github.com/ayaou/command-logger-bundle/commit/232b98cf02525d7a24a6a2f0ad483502f2adaf50))
* semantic release ([3c9a42b](https://github.com/ayaou/command-logger-bundle/commit/3c9a42bf1f49418777ab8e4ae04eadaddfef5d1a))
* update README for testing ([ed92a91](https://github.com/ayaou/command-logger-bundle/commit/ed92a91c13e91fc37d80c302462b6bed70b540df))


### Features

* Configure semantic release ([9185bc0](https://github.com/ayaou/command-logger-bundle/commit/9185bc041eea1f642d94c3e0e8a31193105d6f0d))
* Remove unnecessary file ([0bfdb76](https://github.com/ayaou/command-logger-bundle/commit/0bfdb76fe26d6e4bb97ffee3c5ec414a93cd299e))
* Use attributes instead of configuration ([544a73d](https://github.com/ayaou/command-logger-bundle/commit/544a73de8a9e1324dd9af0ff1d98f94859d12f86))

# 1.0.0 (2025-03-28)


### Bug Fixes

* Adjust pipeline for semantic release ([c7ffc77](https://github.com/ayaou/command-logger-bundle/commit/c7ffc7757652452c8d4563fdaf96b440d8f17c5d))
* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
* Fix release job ([232b98c](https://github.com/ayaou/command-logger-bundle/commit/232b98cf02525d7a24a6a2f0ad483502f2adaf50))
* semantic release ([3c9a42b](https://github.com/ayaou/command-logger-bundle/commit/3c9a42bf1f49418777ab8e4ae04eadaddfef5d1a))
* update README for testing ([ed92a91](https://github.com/ayaou/command-logger-bundle/commit/ed92a91c13e91fc37d80c302462b6bed70b540df))


### Features

* Configure semantic release ([9185bc0](https://github.com/ayaou/command-logger-bundle/commit/9185bc041eea1f642d94c3e0e8a31193105d6f0d))

# 1.0.0 (2025-03-28)


### Bug Fixes

* Adjust pipeline for semantic release ([c7ffc77](https://github.com/ayaou/command-logger-bundle/commit/c7ffc7757652452c8d4563fdaf96b440d8f17c5d))
* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
* Fix release job ([232b98c](https://github.com/ayaou/command-logger-bundle/commit/232b98cf02525d7a24a6a2f0ad483502f2adaf50))
* semantic release ([3c9a42b](https://github.com/ayaou/command-logger-bundle/commit/3c9a42bf1f49418777ab8e4ae04eadaddfef5d1a))
* update README for testing ([ed92a91](https://github.com/ayaou/command-logger-bundle/commit/ed92a91c13e91fc37d80c302462b6bed70b540df))

# 1.0.0 (2025-03-28)


### Bug Fixes

* Adjust pipeline for semantic release ([c7ffc77](https://github.com/ayaou/command-logger-bundle/commit/c7ffc7757652452c8d4563fdaf96b440d8f17c5d))
* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
* Fix release job ([232b98c](https://github.com/ayaou/command-logger-bundle/commit/232b98cf02525d7a24a6a2f0ad483502f2adaf50))
* semantic release ([3c9a42b](https://github.com/ayaou/command-logger-bundle/commit/3c9a42bf1f49418777ab8e4ae04eadaddfef5d1a))

# 1.0.0 (2025-03-27)


### Bug Fixes

* Adjust pipeline for semantic release ([c7ffc77](https://github.com/ayaou/command-logger-bundle/commit/c7ffc7757652452c8d4563fdaf96b440d8f17c5d))
* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
* semantic release ([3c9a42b](https://github.com/ayaou/command-logger-bundle/commit/3c9a42bf1f49418777ab8e4ae04eadaddfef5d1a))

# 1.0.0 (2025-03-27)


### Bug Fixes

* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
* semantic release ([3c9a42b](https://github.com/ayaou/command-logger-bundle/commit/3c9a42bf1f49418777ab8e4ae04eadaddfef5d1a))

# 1.0.0 (2025-03-27)


### Bug Fixes

* Change the release content ([3196911](https://github.com/ayaou/command-logger-bundle/commit/319691115c02949e6248e467c6d43cd4108889c9))
* Fix pipeline ([ff923e1](https://github.com/ayaou/command-logger-bundle/commit/ff923e17761311593ec6be2f2dcb7a616ef727ec))
