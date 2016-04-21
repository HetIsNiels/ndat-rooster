<?php
$url = 'https://tritone.webuntis.com/WebUntis/Timetable.do';
$fields = [];
$action = $_GET['action'] ?? null;

switch ($action) {
	case 'timetable':
		$fields['ajaxCommand'] = 'getWeeklyTimetable';
		$fields['elementType'] = 1;
		$fields['elementId'] = 3637; // id of class
		$fields['date'] = date('Ymd');
		$fields['formatId'] = 3;
		$fields['departmentId'] = 29;
		$fields['filterId'] = -2;
		break;

	case 'classes':
		$fields['ajaxCommand'] = 'getPageConfig';
		$fields['type'] = 1;
		$fields['filter.departmentId'] = -1;
		$fields['formatId'] = 1;
		$fields['id'] = 3637;

		break;
}

$version = null;

if (file_exists(__DIR__ . '/../../version'))
	$version = file_get_contents(__DIR__ . '/../../version');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Cookie: schoolname=_SUQtWm9ldGVybWVlcg==']);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$json = json_decode($response, true);

$result = null;

switch ($action) {
	case 'timetable':
		$elements = [];
		$periods = [];

		foreach ($json['result']['data']['elements'] as $element)
			$elements[$element['id']] = $element;

		$lastTime = null;

		foreach ($json['result']['data']['elementPeriods'][$fields['elementId']] as $period) {
			if ($period['date'] != $fields['date'])
				continue;

			if ($lastTime != null && $lastTime === $period['startTime']) {
				$newPeriod = end($periods);
				array_pop($periods);
			} else {
				$newPeriod = [];
				$newPeriod['id'] = $period['id'];
				$newPeriod['startTime'] = [
					'hour' => substr($period['startTime'], 0, -2),
					'minute' => substr($period['startTime'], -2),
					'total' => ((int)substr($period['startTime'], 0, -2) * 60) + ((int)substr($period['startTime'], -2))
				];
				$newPeriod['endTime'] = [
					'hour' => substr($period['endTime'], 0, -2),
					'minute' => substr($period['endTime'], -2),
					'total' => ((int)substr($period['endTime'], 0, -2) * 60) + ((int)substr($period['endTime'], -2))
				];
				$newPeriod['elements'] = [];
			}

			foreach ($period['elements'] as $element) {
				$found = false;

				if(isset($newPeriod['elements'][$element['type']]))
					foreach ($newPeriod['elements'][$element['type']] as $existingElement)
						if ($existingElement['id'] === $element['id'])
							$found = true;

				if (!$found)
					$newPeriod['elements'][$element['type']][] = $elements[$element['id']];
			}

			$periods[] = $newPeriod;

			$lastTime = $period['startTime'];
		}

		usort($periods, function ($a, $b) {
			return $a['startTime']['total'] - $b['startTime']['total'];
		});

		$result = $periods;
		break;

	default:
		$classes = [];

		foreach($json['elements'] as $element){
			$classes[] = [
				'id' => $element['id'],
				'display_name' => $element['displayname'],
				'long_name' => $element['longName'],
				'name' => $element['name']
			];
		}

		$result = $classes;
		break;
}

$json = [
	'result' => $result,
	'version' => $version
];

header('Content-Type: application/json');
echo json_encode($json);