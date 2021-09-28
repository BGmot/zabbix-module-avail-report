<?php

$output = [
	'body' => (new CPartial('reports.availreport.view.html', $data))->getOutput()
];

if (($messages = getMessages()) !== null) {
	$output['messages'] = $messages->toString();
}

echo json_encode($output);
?>
