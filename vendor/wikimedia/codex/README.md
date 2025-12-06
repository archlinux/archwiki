
# Wikimedia Codex
A PHP library for building HTML and CSS UI components using [Codex](https://doc.wikimedia.org/codex/main/), the Wikimedia design system.

[![Latest Stable Version](https://poser.pugx.org/wikimedia/codex/v?style=for-the-badge)](https://packagist.org/packages/wikimedia/codex)
[![Latest Unstable Version](https://poser.pugx.org/wikimedia/codex/v/unstable?style=for-the-badge)](https://packagist.org/packages/wikimedia/codex)
[![License](https://poser.pugx.org/wikimedia/codex/license?style=for-the-badge)](https://packagist.org/packages/wikimedia/codex)
[![PHP Version Require](https://poser.pugx.org/wikimedia/codex/require/php?style=for-the-badge)](https://packagist.org/packages/wikimedia/codex)

## Installation
Use Composer to install the Codex library:

```bash
composer require wikimedia/codex
```

## Components
The Codex library provides a variety of components to build UI:

- **Accordion**: A collapsible and expandable section for organizing content.
- **Button**: A clickable button that can be styled to reflect different actions.
- **Card**: A component for grouping information and actions related to a single topic.
- **Checkbox**: A form element that allows users to select one or more options.
- **Field**: A container for grouping form elements with optional legend and help a text.
- **InfoChip**: A small component used to display brief information or tags.
- **Label**: A component used to label other form elements.
- **Message**: A component to display information, warnings, or errors to users.
- **Pager**: A component for navigating through pages of data.
- **ProgressBar**: A visual indicator of progress toward a goal or task completion.
- **Radio**: A form element that allows users to select one option from a set.
- **Select**: A dropdown component that allows users to select an option from a list.
- **Table**: A component for arranging data in rows and columns.
- **Tabs**: A component that organizes content into multiple panels with selectable tabs.
- **TextArea**: A multi-line text input field for user input.
- **TextInput**: A single-line text input field for user input.
- **Thumbnail**: A visual component for displaying small preview images.
- **ToggleSwitch**: A ToggleSwitch enables the user to instantly toggle between on and off states.

## Usage
Here is a basic example of how to use the Codex library:

```php
<?php

require 'vendor/autoload.php';
use Wikimedia\Codex\Utility\Codex;

$codex = new Codex();

$accordion = $codex
            ->accordion()
            ->setTitle( "Accordion Example" )
            ->setDescription( "This is an example of an accordion." )
            ->setContentHtml(
                $codex
                    ->htmlSnippet()
                    ->setContent( "<p>This is the content.</p>" )
                    ->build()
            )
            ->setOpen( false )
            ->setAttributes( [
                "class" => "foo",
                "bar" => "baz",
            ] )
            ->build()
            ->getHtml();

echo $accordion;
?>
```

## Scripts

The following scripts are defined for testing and code fixing purposes:

- `test`: Run linting and code checks.
- `fix`: Automatically fix code style issues.
- `phan`: Run the Phan static analyzer.
- `phpcs`: Run the PHP Code Sniffer.
- `start-sandbox`: Start the sandbox environment for testing.

Example usage:

```bash
composer run-script test
composer run-script fix
composer run-script phan
composer run-script phpcs
composer run-script start-sandbox
```

## License
This project is licensed under the GPL-2.0-or-later. See the [LICENSE](LICENSE) file for details.

## Contributing
Please read the [CONTRIBUTING](CONTRIBUTING.md) file for details on our code of conduct, and the process for submitting pull requests to us.

## Bugs
Report bugs at [Phabricator](https://phabricator.wikimedia.org/tag/codex/).

## Homepage
For more information, visit the [homepage](https://doc.wikimedia.org/codex/).
