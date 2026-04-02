<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('jobs:generate-recurring --days=7')->dailyAt('06:00');
Schedule::command('reports:send-scheduled')->dailyAt('07:00');
