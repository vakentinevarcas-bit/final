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
<style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
  }

  body {
    overflow: hidden;
    background-color: #e5e3df;
  }

  #map {
    width: 100vw;
    height: 100vh;
    position: relative;
    z-index: 1;
  }

  .back-btn {
    position: absolute;
    left: 16px;
    top: 16px;
    background: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    cursor: pointer;
    z-index: 1000;
    font-size: 14px;
    color: #3c4043;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
  }

  .back-btn:hover {
    background: #f1f3f4;
  }

  .back-btn svg {
    width: 16px;
    height: 16px;
    fill: #5f6368;
  }

  .custom-attribution {
    position: absolute;
    bottom: 0;
    left: 0;
    font-size: 10px;
    color: #5f6368;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.8);
    padding: 2px 6px;
    border-radius: 4px;
    margin-bottom: 4px;
  }

  .custom-attribution a {
    color: #5f6368;
    text-decoration: none;
  }

  .custom-attribution a:hover {
    text-decoration: underline;
  }

  .leaflet-popup-content-wrapper {
    padding: 0;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 18px 35px rgba(0, 0, 0, 0.18);
    border: 1px solid rgba(0, 0, 0, 0.04);
    background: #fff;
  }

  .leaflet-popup-content {
    margin: 0;
    width: 360px !important;
    max-width: calc(100vw - 24px);
  }

  .leaflet-popup-tip {
    background: #fff;
  }

  .custom-landmark-popup {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    width: 360px;
    max-width: calc(100vw - 24px);
  }

  .custom-landmark-popup .popup-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
    display: block;
    background: linear-gradient(135deg, #dfeaf8, #e8e2d4);
  }

  .custom-landmark-popup .popup-inner {
    padding: 14px 16px 12px;
    background: #fff;
  }

  .custom-landmark-popup .popup-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.25;
    color: #202124;
    margin: 0 0 6px;
  }

  .custom-landmark-popup .popup-description {
    font-size: 12px;
    line-height: 1.5;
    color: #5f6368;
    margin: 0;
  }

  .custom-landmark-popup .popup-tag {
    display: inline-block;
    margin-top: 10px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e8f0fe;
    color: #1a73e8;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: capitalize;
  }

  .custom-landmark-popup .popup-close {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 28px;
    height: 28px;
    border: none;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.8);
    color: #3c4043;
    font-size: 20px;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  }

  /* Modal Styles */
  .modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 2000;
  }

  .modal-content {
    background: #fff;
    border-radius: 12px;
    width: 420px;
    padding: 24px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .modal-header {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }

  .modal-icon {
    width: 28px;
    height: 28px;
    background: #1a73e8;
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
    line-height: 1;
    padding-bottom: 2px;
  }

  .modal-header-text h2 {
    font-size: 18px;
    color: #202124;
    font-weight: 600;
    margin-bottom: 4px;
  }

  .modal-header-text p {
    font-size: 13px;
    color: #5f6368;
  }

  .modal-body {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  .form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .form-group label {
    font-size: 13px;
    color: #3c4043;
    font-weight: 500;
  }

  .form-group input[type="text"],
  .form-group textarea,
  .form-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #dadce0;
    border-radius: 8px;
    font-size: 14px;
    color: #3c4043;
    outline: none;
    transition: border-color 0.2s;
  }

  .form-group input[type="text"]:focus,
  .form-group textarea:focus,
  .form-group select:focus {
    border-color: #1a73e8;
    box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
  }

  .form-group textarea {
    resize: vertical;
    min-height: 60px;
    font-family: Arial, sans-serif;
  }

  .photo-upload {
    border: 2px dashed #dadce0;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #5f6368;
    background: #f8f9fa;
    transition: background 0.2s, border-color 0.2s;
  }

  .photo-upload:hover {
    background: #f1f3f4;
    border-color: #bdc1c6;
  }

  .photo-upload svg {
    width: 32px;
    height: 32px;
    fill: #bdc1c6;
    margin-bottom: 8px;
  }

  .photo-upload span {
    font-size: 13px;
  }

  .modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 8px;
  }

  .modal-footer button {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: background 0.2s;
  }

  .btn-cancel {
    background: #f1f3f4;
    color: #3c4043;
  }

  .btn-cancel:hover {
    background: #e8eaed;
  }

  .btn-save {
    background: #1a73e8;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .btn-save:hover {
    background: #1765cc;
  }

  .btn-save svg {
    width: 16px;
    height: 16px;
    fill: #fff;
  }

  @media (max-width: 768px) {
    .modal-content {
      width: 90%;
      padding: 16px;
    }
  }

  @media (max-width: 480px) {
    .back-btn {
      top: 16px;
      padding: 4px 10px;
      font-size: 11px;
    }
  }
</style>
</head>
<body>
<div id="map"></div>

<div class="back-btn" id="backBtn">
  <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
  Back
</div>

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
      <p id="landmarkSaveStatus" role="status" aria-live="polite" style="display:none;color:#b42318;font-size:13px;"></p>
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
<script>
  const landmarkMarkerStyle = {
    radius: 10,
    color: '#ffffff',
    weight: 2,
    opacity: 1,
    fillColor: '#1a73e8',
    fillOpacity: 1,
    className: 'landmark-marker'
  };

  const leafletMap = L.map('map', {
    zoomControl: false,
    attributionControl: false,
    doubleClickZoom: false
  }).setView([10.9583, 123.3000], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(leafletMap);

  L.circleMarker([10.9583, 123.3000], landmarkMarkerStyle)
    .addTo(leafletMap)
    .bindPopup('<b>Cadiz City</b><br>Negros Occidental, Philippines')
    .openPopup();

  // Modal Elements
  const modal = document.getElementById('landmarkModal');
  const landmarkName = document.getElementById('landmarkName');
  const landmarkDesc = document.getElementById('landmarkDesc');
  const landmarkCategory = document.getElementById('landmarkCategory');
  const cancelBtn = document.getElementById('cancelBtn');
  const saveBtn = document.getElementById('saveBtn');
  const landmarkSaveStatus = document.getElementById('landmarkSaveStatus');
  const photoInput = document.createElement('input');
  photoInput.type = 'file';
  photoInput.accept = 'image/*';
  photoInput.hidden = true;
  document.body.appendChild(photoInput);

  let currentLatLng = null;
  let landmarkPhoto = null;

  function setSaveStatus(message, isError = true) {
    landmarkSaveStatus.textContent = message;
    landmarkSaveStatus.style.display = message ? 'block' : 'none';
    landmarkSaveStatus.style.color = isError ? '#b42318' : '#188038';
  }

  function makeLandmarkPopup(landmark) {
    const content = document.createElement('div');
    content.className = 'custom-landmark-popup';

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'popup-close';
    closeButton.setAttribute('aria-label', 'Close');
    closeButton.textContent = '×';
    closeButton.addEventListener('click', () => {
      const popup = closeButton.closest('.leaflet-popup');
      if (popup) {
        popup.remove();
      }
    });

    const image = document.createElement('img');
    image.className = 'popup-image';
    image.src = landmark.photo || 'https://images.unsplash.com/photo-1500375592092-40eb2168fd21?auto=format&fit=crop&w=900&q=80';
    image.alt = landmark.name;

    const inner = document.createElement('div');
    inner.className = 'popup-inner';

    const title = document.createElement('h3');
    title.className = 'popup-title';
    title.textContent = landmark.name;

    const description = document.createElement('p');
    description.className = 'popup-description';
    description.textContent = landmark.description || 'A scenic destination worth visiting.';

    const category = document.createElement('span');
    category.className = 'popup-tag';
    category.textContent = landmark.category || 'historical';

    inner.appendChild(title);
    inner.appendChild(description);
    inner.appendChild(category);
    content.append(closeButton, image, inner);
    return content;
  }

  function addSavedLandmark(landmark) {
    return L.circleMarker([Number(landmark.lat), Number(landmark.lng)], {
      ...landmarkMarkerStyle,
      fillColor: '#1a73e8'
    })
      .addTo(leafletMap)
      .bindPopup(makeLandmarkPopup({
        name: landmark.name,
        description: landmark.description || '',
        category: landmark.category || 'historical',
        photo: landmark.photo || null
      }));
  }

  function loadSavedLandmarks() {
    fetch('../Cadiz/landmarks_api.php?action=list')
      .then(function (response) {
        if (!response.ok) throw new Error('Could not load saved landmarks.');
        return response.json();
      })
      .then(function (data) {
        if (!data.success || !Array.isArray(data.landmarks)) {
          throw new Error(data.message || 'Could not load saved landmarks.');
        }
        data.landmarks.forEach(addSavedLandmark);
      })
      .catch(function (error) {
        console.error('Error loading saved landmarks:', error);
        setSaveStatus('Saved landmarks could not be loaded. You can still add a new landmark.');
      });
  }

  function openLandmarkModal(latlng = null) {
    currentLatLng = latlng;
    landmarkName.value = '';
    landmarkDesc.value = '';
    landmarkCategory.value = 'historical';
    landmarkPhoto = null;
    photoInput.value = '';
    document.querySelector('#photoUpload span').textContent = 'Click to upload a photo';
    setSaveStatus('');
    modal.style.display = 'flex';
  }

  // Double click on map to open modal
  leafletMap.on('dblclick', function(e) {
    openLandmarkModal(e.latlng);
  });

  // Cancel button
  cancelBtn.addEventListener('click', () => {
    modal.style.display = 'none';
    currentLatLng = null;
  });

  // Save button
  saveBtn.addEventListener('click', async () => {
    if (!currentLatLng || saveBtn.disabled) return;

    const name = landmarkName.value.trim();
    if (!name) {
      setSaveStatus('Please enter a name for this landmark.');
      landmarkName.focus();
      return;
    }

    saveBtn.disabled = true;
    setSaveStatus('Saving landmark…', false);
    try {
      const response = await fetch('../Cadiz/landmarks_api.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: name,
          description: landmarkDesc.value.trim(),
          lat: currentLatLng.lat,
          lng: currentLatLng.lng,
          category: landmarkCategory.value,
          photo: landmarkPhoto
        })
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error(data.message || 'The landmark could not be saved.');
      }

      const savedLandmark = {
        id: data.id,
        name: name,
        description: landmarkDesc.value.trim(),
        lat: currentLatLng.lat,
        lng: currentLatLng.lng,
        category: landmarkCategory.value,
        photo: landmarkPhoto
      };
      addSavedLandmark(savedLandmark).openPopup();
      modal.style.display = 'none';
      currentLatLng = null;
      setSaveStatus('');
    } catch (error) {
      console.error('Error saving landmark:', error);
      setSaveStatus(error.message || 'The landmark could not be saved. Please try again.');
    } finally {
      saveBtn.disabled = false;
    }
  });

  // Close modal when clicking outside
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.style.display = 'none';
      currentLatLng = null;
    }
  });

  document.getElementById('photoUpload').addEventListener('click', () => {
    photoInput.click();
  });

  photoInput.addEventListener('change', () => {
    const file = photoInput.files && photoInput.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
      setSaveStatus('Please choose an image file.');
      photoInput.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      landmarkPhoto = reader.result;
      const span = document.querySelector('#photoUpload span');
      span.textContent = file.name;
      span.style.color = '#1a73e8';
      setSaveStatus('');
    };
    reader.onerror = () => {
      setSaveStatus('The selected photo could not be read. Please choose it again.');
    };
    reader.readAsDataURL(file);
  });

  const backBtn = document.getElementById('backBtn');

  backBtn.addEventListener('click', () => {
    window.location.href = 'admin_dashboard.php';
  });

  loadSavedLandmarks();
</script>
</body>
</html>