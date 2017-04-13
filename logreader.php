<?php
// Debugging output functions
function debug_out($variable, $die = false) {
	$trace = debug_backtrace()[0];
	echo '<pre style="background-color: #f2f2f2; border: 2px solid black; border-radius: 5px; padding: 5px; margin: 5px;">'.$trace['file'].':'.$trace['line']."\n\n".print_r($variable, true).'</pre>';
	if ($die) { http_response_code(503); die(); }
}

$map = array(
	1 => 'timestamp',
	2 => 'object',
	3 => 'level',
	4 => 'pid',
	5 => 'client',
	6 => 'process',
	7 => 'error',
	8 => 'file',
	9 => 'line',
);

if ($_GET && isset($_GET['log'])) {
	if (isset($_GET['simerr'])) {
		error_log("Simulated Error", 0, "Simulated Error");
		die();
	}
	
	if (isset($_GET['clearlog'])) {
		@file_put_contents($_GET['log'], '');
		die();
	}
	
	@$handle = fopen($_GET['log'], "r");
	$curLine = 1;
	$lines = array();
	$raw = array();
	if ($handle) {
		while (($line = fgets($handle)) !== false) {
			if (!isset($_GET['start']) || $_GET['start'] <= $curLine) {
				if (preg_match('/\[([\w\d\s:.]+)\] \[([\w\d\s.]*):([\w\d\s.]*)\] \[pid (\d+)\] (?:\[client ([\w.:]+)\] )?([\w, ]+): ?(.+?(?=in (\/.+?)(?= on line (\d+)))|.*)/',$line,$match)) {
					if (trim($match[6]) !== 'PHP Stack trace') {
						$raw[$curLine] = $match;
						$tmp = array('logline' => $curLine);
						foreach($map as $key => $value) {
							if (isset($match[$key]) && $match[$key]) {
								$tmp[$value] = trim($match[$key]);
							}
						}
						$lines[] = $tmp;
					}
				}
			}
			$curLine ++;
		}

		fclose($handle);
	} else {
		if (isset($_GET['debug']) && $_GET['debug'] = 'raw') {
			debug_out('File Exists: '.file_exists($_GET['log']));
			debug_out('Couldn\'t Open File: '.$_GET['log'],1);
		} else {
			header('content-type: application/json');
			// Err Data
			if (!isset($_GET['start']) || $_GET['start'] == 0) {
				$data = array(array(
					'logline' => 0, 
					'timestamp' => time(), 
					'object' => 'Log Reader API', 
					'level' => 'Fatal', 
					'Process' => 'PHP', 
					'error' => 'Could not Open File'
				));
			} else {
				$data = array();
			}
			echo json_encode(array('data' => $data, 'stop' => true));
			die();
		}
	} 
	
	if (isset($_GET['debug']) && $_GET['debug'] = 'raw') {
		debug_out($raw,1);
	} else if (isset($_GET['debug'])) {
		debug_out($lines,1);
	} else {
		header('content-type: application/json');
		echo json_encode(array('data' => $lines));
	}
	die();
}
?>
<!DOCTYPE html>
<html>
	<head>
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/css/bootstrap.min.css" integrity="sha384-rwoIResjU2yc3z8GV/NPeZWAv56rSmLldC3R/AZzGRnGxQQKnKkoFVhFQhNUwEyJ" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.2.4/css/buttons.dataTables.min.css" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.datatables.net/fixedheader/3.1.2/css/fixedHeader.dataTables.min.css" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.1.1/css/responsive.dataTables.min.css" crossorigin="anonymous">
		<link rel="stylesheet" href="https://cdn.datatables.net/scroller/1.4.2/css/scroller.dataTables.min.css" crossorigin="anonymous">
		
		<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
		<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js" integrity="sha256-VazP97ZCwtekAsvgPBSUwPFKdrwD3unUfSGVYrahUqU=" crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous" defer></script>
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous" defer></script>
		<script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js" crossorigin="anonymous" defer></script>
		<script src="https://cdn.datatables.net/buttons/1.2.4/js/dataTables.buttons.min.js" crossorigin="anonymous" defer></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js" crossorigin="anonymous" defer></script>
		<script src="https://cdn.datatables.net/fixedheader/3.1.2/js/dataTables.fixedHeader.min.js" crossorigin="anonymous" defer></script>
		<script src="https://cdn.datatables.net/responsive/2.1.1/js/dataTables.responsive.min.js" crossorigin="anonymous" defer></script>
		<script src="https://cdn.datatables.net/scroller/1.4.2/js/dataTables.scroller.min.js" crossorigin="anonymous" defer></script>
		
		<script defer>
			var currentLine = 0;
			var timeoutDuration = 5000;
			var fadeTime = 1500;
			var getRowsStatus = true;
			var datatable = {};
			var ajaxLink = location.href.split('#')[0].replace('?file=/','?log=/');
			var timeoutObj = {};
			
			function getRows() {
				$.get(ajaxLink+'&start='+currentLine, {}, function(data) {
					// Load Stuff Into The Table
					$.each(data.data, function(i,v) {
						if (currentLine <= v.logline) { currentLine = v.logline + 1; }
						datatable.row.add(v);
					});
					datatable.draw();
					
					// Highlight
					var $newRows = $('#output tbody tr:nth-child(-n+'+data.data.length+')');
					$newRows.animate({backgroundColor: '#5bc0de'});
					setTimeout(function() {
						$newRows.animate({backgroundColor: 'transparent'});
					}, fadeTime);
					
					// Set Next Request Timeout
					if (typeof data.stop === 'undefined' && getRowsStatus) {
						timeoutObj = setTimeout(getRows, timeoutDuration);
					}
				});
			}
			
			function resetGetRows() {
				window.clearTimeout(timeoutObj);
				getRows();
			}
			
			function toggleGetRows(element) {
				if (getRowsStatus) {
					getRowsStatus = false;
					element.className = 'btn btn-danger';
					element.innerHTML = 'Inactive';
				} else {
					getRowsStatus = true;
					timeoutObj = setTimeout(getRows, timeoutDuration);
					element.className = 'btn btn-success';
					element.innerHTML = 'Active';
				}
			}
			
			function simulateError() {
				$.get(ajaxLink+'&simerr', {}, function(data) {
					console.log('complete');
				});
			}
			
			function clearLog() {
				if (confirm('Are you sure you want to clear the log?')) {
					window.clearTimeout(timeoutObj);
					$.get(ajaxLink+'&clearlog', {}, function(data) {
						currentLine = 0;
						datatable.clear().draw();
						
						if (getRowsStatus) {
							getRows();
						}
					});
				}
			}
			
			$(document).ready(function() {
				datatable = $('#output').DataTable({
					dom: 'lBfrtip',
					fixedHeader: { 
						header: true, 
						headerOffset: $('.nav-wrapper').outerHeight(), 
					},
					language: {},
					data: [],
					searchDelay: 500,
					pageLength: 50,
					deferRender: true,
					stateSave: false,
					buttons: { 
						buttons: [
							{ text: 'Copy', extend: 'copy', },
							{ text: 'Export', extend: 'excel', },
						],
					},
					initComplete: function(settings, json) {
						var $thisDtable = this;
						var $thisDwrapper = this.parent('.dataTables_wrapper');
						
						// Rejig Search so its delay from last keyup
						var searchDelay = null;
						var $searchField = $thisDwrapper.find('.dataTables_filter input[type=search]');
						$searchField.off('keyup.DT input.DT').on('keyup', function() {
							var search = $searchField.val();
							clearTimeout(searchDelay);
							searchDelay = setTimeout(function() {
							if (search != null) { $thisDtable.api().search(search).draw() } }, settings.searchDelay);
						});
						
						// Move field into Nav
						$searchField = $searchField.attr('class', 'form-control mr-sm-2').attr('placeholder', 'Search').detach()
						$('#replaceme').replaceWith($searchField);
						$('#output_filter').remove();
						
						// Move length selector
						var $lengthField = $('select[name=output_length]').attr('class', 'form-control').attr('aria-describedby', 'basic-addon1').detach();
						$('#replacemelength').replaceWith($lengthField);
						$('#output_length').remove();
					},
					drawCallback: function(settings) {
						
					},
					columns: [
						{title: 'Log Line', data: 'logline', defaultContent: ''},
						{title: 'Timestamp', data: 'timestamp', defaultContent: '',},
						{title: 'Object', data: 'object', defaultContent: '',},
						{title: 'Level', data: 'level', defaultContent: '',},
						{title: 'PID', data: 'pid', defaultContent: '',},
						{title: 'Client', data: 'client', defaultContent: '',},
						{title: 'Process', data: 'process', defaultContent: '',},
						{title: 'Error', data: 'error', defaultContent: '', width: '50%'},
						{title: 'File', data: 'file', defaultContent: '',},
						{title: 'Line', data: 'line', defaultContent: '',},
					],
					order: [[0, 'desc']],
				});
				
				getRows();
			});
		</script>
		
		<style>
			#output_wrapper {
				width: 100%;
			}
			.container-fluid {
				padding-top: 10px;
				padding-left: 20px;
				padding-right: 20px;
			}
			.input-group {
				margin: 2px;
			}
		</style>
	</head>
	<body>
	<header>
		<nav class="navbar navbar-toggleable-md navbar-light bg-faded">
			<button class="navbar-toggler navbar-toggler-right" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<a class="navbar-brand">Log Reader</a>

			<div class="collapse navbar-collapse" id="navbarSupportedContent">
				<ul class="navbar-nav mr-auto">
					<li class="nav-item active">
						<a class="nav-link" onclick="location.reload();" href="#">Reload Page</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" onclick="simulateError();" href="#">Simulate Error</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" onclick="clearLog();" href="#">Clear Log</a>
					</li>
				</ul>
				<form class="form-inline my-2 my-lg-0">
					<div class="input-group">
						<button id="refreshStatus" type="button" class="btn btn-success" onclick="toggleGetRows(this);" value="1">Active</button>
					</div>
					<div class="input-group">
						<span class="input-group-addon" id="basic-addon1">Fade Time</span>
						<select id="fadeSelect" class="form-control" aria-describedby="basic-addon1" onchange="fadeTime = this.value;">
							<option value="500">500 ms</option>
							<option value="1000">1000 ms</option>
							<option value="1500" selected>1500 ms</option>
							<option value="2500">2500 ms</option>
							<option value="5000">2500 ms</option>
							<option value="7500">7500 ms</option>
							<option value="10000">10 seconds</option>
							<option value="20000">20 seconds</option>
							<option value="30000">30 seconds</option>
							<option value="45000">45 seconds</option>
							<option value="60000">60 seconds</option>
						</select>
					</div> 
					<div class="input-group">
						<span class="input-group-addon" id="basic-addon2">Update Rate</span>
						<select id="refreshSelect" class="form-control" aria-describedby="basic-addon2" onchange="timeoutDuration = this.value; resetGetRows();">
							<option value="500">500 ms</option>
							<option value="1000">1000 ms</option>
							<option value="1500">1500 ms</option>
							<option value="2500">2500 ms</option>
							<option value="5000" selected>5000 ms</option>
							<option value="7500">7500 ms</option>
							<option value="10000">10 seconds</option>
							<option value="20000">20 seconds</option>
							<option value="30000">30 seconds</option>
							<option value="45000">45 seconds</option>
							<option value="60000">60 seconds</option>
						</select>
					</div> 
					<div class="input-group">
						<span class="input-group-addon" id="basic-addon3">Show</span>
						<select id="replacemelength" class="form-control" aria-describedby="basic-addon3">
						
						</select>
					</div> 
					<input id="replaceme" class="form-control mr-sm-2" type="text" placeholder="Search">
				</form>
			</div>
			</nav>
	</header>
		<div class="container-fluid">
			<div class="row">
				
			</div>
			<div class="row">
				<table id="output" class="display" cellspacing="0" width="100%"></table>
			</div>
		</div>
	<body>
</html>