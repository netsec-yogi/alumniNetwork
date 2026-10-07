<?php

namespace App\Services\Uploads;

use InvalidArgumentException;

/** An upload refused for a reason the user can be shown. */
class UploadRejected extends InvalidArgumentException {}
