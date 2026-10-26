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
  let pendingMarker = null;

  function clearPendingMarker() {
    if (pendingMarker) {
      leafletMap.removeLayer(pendingMarker);
      pendingMarker = null;
    }
  }

  function placePendingPin(latlng) {
    clearPendingMarker();
    pendingMarker = L.circleMarker(latlng, {
      ...landmarkMarkerStyle,
      fillColor: '#f59e0b'
    })
      .addTo(leafletMap)
      .on('click', function(e) {
        L.DomEvent.stopPropagation(e);
        openLandmarkModal(e.latlng);
      });
  }

  function setSaveStatus(message, isError = true) {
    landmarkSaveStatus.textContent = message;
    landmarkSaveStatus.classList.toggle('is-visible', Boolean(message));
    landmarkSaveStatus.classList.toggle('is-success', !isError);
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
    const photoUploadLabel = document.querySelector('#photoUpload span');
    photoUploadLabel.textContent = 'Click to upload a photo';
    photoUploadLabel.classList.remove('is-selected');
    setSaveStatus('');
    modal.classList.add('is-open');
  }

  // Click the map to place a pin, then click the pin to enter landmark details
  leafletMap.on('click', function(e) {
    placePendingPin(e.latlng);
  });

  document.addEventListener('keydown', function(e) {
    if (e.key.toLowerCase() !== 'p' || e.repeat || modal.classList.contains('is-open')) return;
    if (e.target instanceof HTMLElement &&
        (e.target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName))) return;
    e.preventDefault();
    placePendingPin(leafletMap.getCenter());
  });

  // Cancel button
  cancelBtn.addEventListener('click', () => {
    modal.classList.remove('is-open');
    currentLatLng = null;
    clearPendingMarker();
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
      clearPendingMarker();
      addSavedLandmark(savedLandmark).openPopup();
      modal.classList.remove('is-open');
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
      modal.classList.remove('is-open');
      currentLatLng = null;
      clearPendingMarker();
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
      span.classList.add('is-selected');
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
