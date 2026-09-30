<?php
render('simple', [
    'title' => 'Er ging iets mis',
    'message' => $message ?? 'Probeer het later opnieuw.',
]);
