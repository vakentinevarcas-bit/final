<?php
require_once __DIR__ . '/../Cadiz/session.php';

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Google Maps UI - Cadiz City</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="../css/admin_map.css">
</head>
<body>
<div id="map"></div>

<div class="back-btn" id="backBtn">
  <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
  Back
</div>

<div class="map-hint">Press P to place a pin at the map center, or click the map. Click the pin to add details.</div>

<div class="custom-attribution">
  &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> contributors
</div>

<!-- Add Landmark Modal -->
<div class="modal-overlay" id="landmarkModal">
  <div class="modal-content">
    <div class="modal-header">
      <div class="modal-icon">+</div>
      <div class="modal-header-text">
        <h2>Add Landmark</h2>
        <p>Position set. Fill in the details and save.</p>
      </div>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label for="landmarkName">Name</label>
        <input type="text" id="landmarkName" placeholder="e.g. Central Park">
      </div>
      <div class="form-group">
        <label for="landmarkDesc">Description</label>
        <textarea id="landmarkDesc" placeholder="A short note..."></textarea>
      </div>
      <div class="form-group">
        <label for="landmarkCategory">Category</label>
        <select id="landmarkCategory">
          <option value="historical">Historical</option>
          <option value="food">Food &amp; Drink</option>
          <option value="beaches">Beaches</option>
          <option value="shopping">Shopping</option>
          <option value="hotel">Hotel</option>
        </select>
      </div>
      <div class="form-group">
        <label>Photo</label>
        <div class="photo-upload" id="photoUpload">
          <svg viewBox="0 0 24 24"><path d="M12 12m-3.2 0a3.2 3.2 0 1 0 6.4 0a3.2 3.2 0 1 0 -6.4 0M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>
          <span>Click to upload a photo</span>
        </div>
      </div>
      <p id="landmarkSaveStatus" class="landmark-save-status" role="status" aria-live="polite"></p>
    </div>
    <div class="modal-footer">
      <button class="btn-cancel" id="cancelBtn">Cancel</button>
      <button class="btn-save" id="saveBtn">
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        Save
      </button>
    </div>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../js/admin_map.js"></script>
</body>
</html>