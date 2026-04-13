<?php
/** @var \Bvf\Auth\User $user */
/** @var string $title */
/** @var string $nav_active */
/** @var array{message:string,type:string}|null $flash */
/** @var string $content */
require __DIR__ . '/partials/header.php';
echo $content;
require __DIR__ . '/partials/footer.php';
