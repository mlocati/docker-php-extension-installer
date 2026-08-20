# How to contribute

## Formatting code

Before submitting any pull request, you should execute the `lint` script in the `scripts` directory (or `lint.bat` on Windows).

If you don't do that, and if there's a coding style error, you'll see that the `Check shell coding style` and/or the `Check PHP coding style` GitHub Actions will fail.

The error will be something like this:

```diff
--- filename.orig
+++ filename
@@ -line number,7 +line number,7 @@
     good line of code #1
     good line of code #2
     good line of code #3
-    the original line with a wrong coding style
+    the line wrong coding style that has been corrected
     good line of code #4
     good line of code #5
     good line of code #6
```

So, you should fix highlighted line (the one(s) at `line number`) by replacing what you see after the `-` with what you see after the `+`

## Adding support to a new PHP extension?

1. change the `install-php-extensions` script
2. update the `data/supported-extensions` file, adding a new line with the handle of the extension and the list of supported PHP versions
3. if the extension requires ZTS images:  
   add a new line to the `data/special-requirements` file, with the extension handle followed by a space and `zts`

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/60) for an example.

## Changing the supported PHP versions for an already supported PHP extension?

1. change the `install-php-extensions` script
2. update the `data/supported-extensions` file, adding the new PHP version to the existing line corresponding to the updated extension

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/62) for an example.

## Improving code for an already supported extension?

If you change some code that affects one or more extensions, please add a line with `Test: extension1, extension2` to the message of one of the pull request commits.
That way, the test jobs will check the extension even if you don't touch the `data/supported-extensions` file.

Here's an example of a commit message:

```
Improve the GD and ZIP extensions

Test: gd, zip
```

Tests only check the installation of a single PHP extension at a time.
If you want to test installing more PHP extensions at the same time, use a commit message like this:

```
Improve the GD and ZIP extensions

Test: gd+zip
```

If your pull request contains multiple commits, we'll check the "Test:" message of every commit.
If you want to stop parsing next commits, add `-STOP-` in the "Test:" line, for example:

```
Improve the GD and ZIP extensions

Test: gd, zip, -STOP-
```

See [this pull request](https://github.com/mlocati/docker-php-extension-installer/pull/43) for an example.

## PHP requirements and configure options

PHP extensions published on the PECL archive contain a `package.xml` (or `package2.xml`) file describing the supported PHP versions and the options that can be used to compile it.
When we add support for a new PHP extension, and when a new version of a PHP extension is released, we have to check those constraints.

It's a rather tedious task, so I developed a project that lets you easily check those constraints: you can find it at https://mlocati.github.io/pecl-info ([here](https://github.com/mlocati/pecl-info) you can find its source code).
