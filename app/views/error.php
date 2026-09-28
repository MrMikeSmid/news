<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Er ging iets mis</title>
<style>
body{font-family:-apple-system,BlinkMacSystemFont,"Inter",sans-serif;background:#FAF9F5;color:#1F1E1D;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0}
.box{max-width:420px;text-align:center;padding:40px}
h1{font-size:22px;margin-bottom:8px}
p{color:#6B6B68}
</style>
</head>
<body>
<div class="box">
<h1>Er ging iets mis</h1>
<p><?= e($message ?? 'Probeer het later opnieuw.') ?></p>
</div>
</body>
</html>
