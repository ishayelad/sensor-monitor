<?php
require_once __DIR__ . '/../models/DB.php';
require_once __DIR__ . '/functions.php';

// Check db & tables existence on startup
DB::initializeDb();
DB::initializeTables();