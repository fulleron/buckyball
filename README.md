Buckyball PHP Framework
=======================

Main goals:
-----------

* PHP is fun again.
* Decoupling everything that should not be coupled.
* In development mode logging which module/file changes what.
* On frontend access info about and enable/disable modules.
* Everything non essential is a module, and can be disabled.
* Module files are confined to one folder.
* Keep folder structure and number of files as minimal as possible.
* Module requires only bootstrap callback. Everything else is up to the developer.
* Bootstrap callback only injects module's callbacks into request.
* Do not force to use classes.
* IDE friendly (autocomplete, phpdoc, etc)
* Developer friendly (different file names in IDE tabs)
* Debug friendly (concise print_r, debugbacktrace, debug augmentation GUI on frontend)
* Versioning system friendly (module confined to 1 folder)
* Do not fight or stronghand with the developer, do not try to force, limit, etc.
* Developer will find ways to work around or use undocumented API.
* Conserve memory by not storing unnecessary data or configuration more than needed.
* Minimize framework overhead (~10ms on slow server)