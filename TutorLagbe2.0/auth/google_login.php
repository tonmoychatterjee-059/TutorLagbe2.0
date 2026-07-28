<?php
require_once __DIR__ . '/../includes/bootstrap.php';
/*
 * Google OAuth placeholder. Before enabling it, install a maintained OAuth client,
 * add GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET as environment variables, generate
 * the authorization URL with a verified redirect URI, and verify Google's callback
 * ID token server-side before creating or logging in a user.
 */
flash('success', 'Google login is being prepared. Please use email and password for now.');
header('Location: login.php'); exit;
