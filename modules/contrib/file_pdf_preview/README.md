CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers
 * Troubleshooting

# Introduction

Provides a Field Widget which generate a preview image from a PDF file

# REQUIREMENTS

This module requires the following module:

  * The drupal core file module
  * The vendor library spatie/pdf-to-image
  * The imagick extension on your server

# INSTALLATION

Install the module as you would normally install a
contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
further information.

Use `composer require drupal/file_pdf_preview` to download the dependencies automatically with composer.

# CONFIGURATION

    1. Select the widget "File PDF Preview" in the Manage form display page
       settings for the File field you set on the entity.
    2. Configure the behavior in the widget settings.
    3. A preview image of the PDF is generated.
    4. If a target field name is set on the widget settings, the preview image
       will ba saved into this field.

# MAINTAINERS

Current maintainers:

 * Flocon de toile
   Website: https://www.flocondetoile.fr
   Drupal: https://www.drupal.org/u/flocondetoile

# TROUBLESHOOTING

If the PDF generation doesn't work and you see these errors in your logs:

```
attempt to perform an operation not allowed by the security policy `PDF' @ error/constitute.c/IsCoderAuthorized/408
```

Then you need to change the policy of Imagick on your server.

In the file /etc/ImageMagick-6/policy.xml

Looks at this line

```
<policy domain="coder" rights="none" pattern="PDF" />
```

and change to

```
<policy domain="coder" rights="read|write" pattern="PDF" />
```
