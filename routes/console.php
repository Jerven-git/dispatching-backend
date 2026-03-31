<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:generate-recurring --days=7')->dailyAt('06:00');
