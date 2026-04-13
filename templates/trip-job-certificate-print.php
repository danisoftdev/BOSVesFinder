<?php
/** @var array<string,mixed> $certificate */
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Job certificate — Trip #<?php echo (int) ( $certificate['trip_id'] ?? 0 ); ?></title>
	<link rel="stylesheet" href="/assets/css/job-certificate.css">
</head>
<body class="bvf-cert-print-doc">
	<p class="bvf-cert-print-actions no-print">
		<button type="button" class="bvf-btn" id="bvf-cert-print-go">Print</button>
		<a class="bvf-btn bvf-btn--secondary" href="/app/trips/<?php echo (int) ( $certificate['trip_id'] ?? 0 ); ?>/job-certificate">Back to edit</a>
	</p>
	<?php require __DIR__ . '/partials/job-certificate-sheet.php'; ?>
	<script>
	document.getElementById('bvf-cert-print-go')?.addEventListener('click', function () { window.print(); });
	</script>
</body>
</html>
