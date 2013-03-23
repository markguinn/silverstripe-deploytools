Deployment Tools Module for Silverstripe
========================================

Provides automated git deployment and (hopefully soon) assets/database backup.
This module is in a very early state has been very helpful to us already so
I'm putting it out there. Feedback and pull requests are very welcome.

FEATURES:
---------
* Provides a commit hook endpoint for automated git deployment (currently only from Bitbucket and Github)
	- clears cache
	- builds database (dev/build)
	- If composer.json is found, runs "composer install"
* Provides easy installation for:
	- setting up the post-commit service hook on Bitbucket
	- others to come

INSTALLATION:
-------------
1. Install via composer (composer require markguinn/silverstripe-deploytools) or
   download and install manually.
2. Open /deploy-tools/install in a browser and fill out the form.
3. Add "[deploy]" deploy to your commit message for automatic deployment when you push to master.

TODO:
-----
* refactor some things to make services hooks etc more pluggable
* host backup cron job
* should have a hook for updating itself as well
* install tool
	- setup cron job
	- possibly register with a central server if we ever get that going
* register events with central server (backup, deploy, etc)
* github support
* add some configuration (change or remove [deploy] tag, etc)
* look into rollbacks and atomic deployment
* deploy from other branches
* cron job for cache cleanup (i.e. old files if you're using a rolling key system)

DEVELOPERS:
-----------
* Mark Guinn - mark@adaircreative.com

LICENSE (MIT):
--------------
Copyright (c) 2013 Mark Guinn

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the
Software, and to permit persons to whom the Software is furnished to do so, subject
to the following conditions:

The above copyright notice and this permission notice shall be included in all copies
or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.