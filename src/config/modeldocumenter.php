<?php

return [
	'modelPath' => 'App',
	// If recursive, it will scan php files in subfolders as well in its search for Models
	'recursive' => true,
	// Which lineendings does your project use? lf|cr|crlf|lfcr
	'lineendings' => 'lf',
	// Import Carbon\Carbon if there are any Carbons in the docblock properties?
	'importCarbon' => false,
	// Add any custom modules here
	'modules' => [
	],
	'options' => [
		// \Enz0project\ModelDocumenter\ModelDocumenterOptions::SORT_DOCBLOCK => \Enz0project\ModelDocumenter\ModelDocumenterOptions::SORT_NAME_ASC,
	],
];
