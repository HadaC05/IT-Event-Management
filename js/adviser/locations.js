window.SharedNavigation.ready.then((context) => {
    'use strict';

    const API = 'api/adviser-locations.php';
    const form = document.querySelector('[data-location-form]');
    const dialog = document.querySelector('[data-location-dialog]');
    const rows = document.querySelector('[data-location-rows]');
    const count = document.querySelector('[data-location-count]');
    const filters = document.querySelector('[data-location-filters]');
    const clearFilters = document.querySelector('[data-clear-location-filters]');
    const pagination = document.querySelector('[data-location-pagination]');
    const gpsButton = document.querySelector('[data-gps-toggle]');
    const refreshGpsButton = document.querySelector('[data-gps-refresh]');
    const applyButton = document.querySelector('[data-apply-coordinates]');
    const manualButton = document.querySelector('[data-manual-coordinates]');
    const saveButton = document.querySelector('[data-save-location]');
    const gpsMessage = document.querySelector('[data-gps-message]');
    const liveLatitude = document.querySelector('[data-live-latitude]');
    const liveLongitude = document.querySelector('[data-live-longitude]');
    const liveAccuracy = document.querySelector('[data-live-accuracy]');
    const liveTime = document.querySelector('[data-live-time]');
    const accuracyWarning = document.querySelector('[data-accuracy-warning]');
    const accuracyWarningText = document.querySelector('[data-accuracy-warning-text]');
    const preview = document.querySelector('[data-range-preview]');
    const rangeState = document.querySelector('[data-range-state]');
    const rangeDetails = document.querySelector('[data-range-details]');
    const parentLocationWrap = document.querySelector('[data-parent-location-wrap]');
    const parentLocationSelect = form.elements.parent_location_id;
    let csrfToken = context.csrfToken;
    let locations = [];
    let generalLocations = [];
    let currentPage = Math.max(1, Number(new URLSearchParams(location.search).get('page')) || 1);
    let watchId = null;
    let currentPosition = null;
    let manualCoordinates = false;

    form.dataset.axiosForm = '';
    filters.dataset.axiosForm = '';

    const notify = (type, message) => window.Notifications?.[type]?.(message);
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character]);
    const coordinate = value => Number(value).toFixed(7);
    const formatDate = value => value ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T'))) : '-';

    const longitudeDifference = (left, right) => {
        const difference = Math.abs(left - right) % 360;
        return difference > 180 ? 360 - difference : difference;
    };

    const boxState = (latitude, longitude, centerLatitude, centerLongitude, radius) => {
        const latitudeDelta = radius / 111320;
        const longitudeScale = Math.max(Math.cos(centerLatitude * Math.PI / 180), 0.000001);
        const longitudeDelta = radius / (111320 * longitudeScale);
        return {
            inside: Math.abs(latitude - centerLatitude) <= latitudeDelta && longitudeDifference(longitude, centerLongitude) <= longitudeDelta,
            north: centerLatitude + latitudeDelta,
            south: centerLatitude - latitudeDelta,
            east: centerLongitude + longitudeDelta,
            west: centerLongitude - longitudeDelta,
        };
    };

    const renderRangePreview = () => {
        const latitude = Number(form.elements.latitude.value);
        const longitude = Number(form.elements.longitude.value);
        const radius = Number(form.elements.radius.value);
        if (!form.elements.latitude.value || !form.elements.longitude.value || !(radius > 0)) {
            preview.classList.add('hidden');
            return;
        }
        const reference = currentPosition || { latitude, longitude };
        const state = boxState(reference.latitude, reference.longitude, latitude, longitude, radius);
        preview.classList.remove('hidden', 'border-[#397565]/25', 'bg-[#397565]/5', 'border-[#FF6B2C]/25', 'bg-[#FF6B2C]/7');
        preview.classList.add(!currentPosition || state.inside ? 'border-[#397565]/25' : 'border-[#FF6B2C]/25', !currentPosition || state.inside ? 'bg-[#397565]/5' : 'bg-[#FF6B2C]/7');
        rangeState.textContent = currentPosition ? (state.inside ? 'Current GPS is inside this box' : 'Current GPS is outside this box') : 'Square boundary is ready';
        rangeState.className = `block text-sm ${!currentPosition || state.inside ? 'text-[#397565]' : 'text-[#D64A12]'}`;
        rangeDetails.textContent = `Boundary: ${coordinate(state.south)} to ${coordinate(state.north)} latitude; ${coordinate(state.west)} to ${coordinate(state.east)} longitude.`;
    };

    const positionReceived = position => {
        if (watchId === null) return;
        currentPosition = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy, timestamp: position.timestamp };
        liveLatitude.textContent = coordinate(currentPosition.latitude);
        liveLongitude.textContent = coordinate(currentPosition.longitude);
        liveAccuracy.textContent = `+/- ${Math.round(currentPosition.accuracy)} m`;
        liveTime.textContent = new Intl.DateTimeFormat('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' }).format(new Date(position.timestamp));
        const accuracyMeters = Math.round(currentPosition.accuracy);
        const warningLevel = currentPosition.accuracy >= 50 ? 'red' : currentPosition.accuracy > 20 ? 'yellow' : 'green';
        accuracyWarning.classList.remove('hidden');
        accuracyWarning.classList.add('flex');
        accuracyWarning.classList.remove('border-[#397565]/30', 'bg-[#397565]/10', 'text-[#397565]', 'border-amber-200', 'bg-amber-50', 'text-amber-700', 'border-[#FF6B2C]/30', 'bg-[#FF6B2C]/10', 'text-[#D64A12]');
        if (warningLevel === 'green') {
            accuracyWarning.classList.add('border-[#397565]/30', 'bg-[#397565]/10', 'text-[#397565]');
            accuracyWarningText.textContent = `High GPS accuracy. Estimated position uncertainty is up to ${accuracyMeters.toLocaleString('en-PH')} meters. This reading is suitable for setting the location boundary.`;
        } else if (warningLevel === 'yellow') {
            accuracyWarning.classList.add('border-amber-200', 'bg-amber-50', 'text-amber-700');
            accuracyWarningText.textContent = `Moderate GPS accuracy. Your reported position may differ from your actual position by up to ${accuracyMeters.toLocaleString('en-PH')} meters. You may apply it, but a better reading is recommended for a smaller boundary.`;
        } else if (warningLevel === 'red') {
            accuracyWarning.classList.add('border-[#FF6B2C]/30', 'bg-[#FF6B2C]/10', 'text-[#D64A12]');
            accuracyWarningText.textContent = `Low GPS accuracy. Your reported position may differ from your actual position by up to ${accuracyMeters.toLocaleString('en-PH')} meters. You may still apply it, but a new reading or larger boundary is strongly recommended.`;
        }
        gpsMessage.textContent = 'Receiving live coordinate updates.';
        applyButton.disabled = false;
        renderRangePreview();
    };

    const positionFailed = error => {
        const messages = { 1: 'Location access was denied. Allow it in browser and device settings, or enter coordinates manually.', 2: 'Your device could not determine its location. You can enter coordinates manually.', 3: 'The GPS request timed out. Try again or enter coordinates manually.' };
        gpsMessage.textContent = messages[error.code] || 'Live GPS is unavailable.';
        notify('error', gpsMessage.textContent);
    };

    const stopGps = () => {
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        watchId = null;
        gpsButton.textContent = 'Turn On GPS';
        gpsButton.classList.remove('bg-[#FF6B2C]');
        gpsButton.classList.add('bg-[#397565]');
        refreshGpsButton.disabled = true;
        gpsMessage.textContent = currentPosition ? 'GPS paused. The last live reading remains available.' : 'GPS is turned off.';
    };

    const resetGps = () => {
        stopGps();
        currentPosition = null;
        liveLatitude.textContent = '-';
        liveLongitude.textContent = '-';
        liveAccuracy.textContent = '-';
        liveTime.textContent = '-';
        accuracyWarning.classList.add('hidden');
        accuracyWarning.classList.remove('flex');
        gpsMessage.textContent = 'GPS is turned off.';
        applyButton.disabled = true;
    };

    const toggleGps = () => {
        if (watchId !== null) { stopGps(); return; }
        if (!window.isSecureContext) { gpsMessage.textContent = 'Browser GPS requires HTTPS or localhost. Use a trusted HTTPS address or enter coordinates manually.'; notify('error', gpsMessage.textContent); return; }
        if (!('geolocation' in navigator)) { gpsMessage.textContent = 'This browser does not offer device location. Enter coordinates manually.'; notify('error', gpsMessage.textContent); return; }
        gpsMessage.textContent = 'Waiting for a live GPS reading...';
        gpsButton.textContent = 'Turn Off GPS';
        gpsButton.classList.remove('bg-[#397565]');
        gpsButton.classList.add('bg-[#FF6B2C]');
        refreshGpsButton.disabled = false;
        watchId = navigator.geolocation.watchPosition(positionReceived, positionFailed, { enableHighAccuracy: true, maximumAge: 5000, timeout: 8000 });
    };

    const refreshGps = () => {
        if (watchId === null || !('geolocation' in navigator)) return;
        refreshGpsButton.disabled = true;
        gpsMessage.textContent = 'Requesting a fresh GPS reading...';
        navigator.geolocation.getCurrentPosition(
            position => {
                positionReceived(position);
                refreshGpsButton.disabled = watchId === null;
            },
            error => {
                if (watchId !== null) {
                    gpsMessage.textContent = currentPosition
                        ? 'No newer reading arrived. Continuing with the latest live GPS position.'
                        : 'A fresh reading is taking longer than expected. Live GPS is still running.';
                    // This status is already visible beside the GPS controls.
                }
                refreshGpsButton.disabled = watchId === null;
            },
            { enableHighAccuracy: true, maximumAge: 10000, timeout: 5000 },
        );
    };

    const paginationButton = (label, page, disabled = false) => `<button class="min-h-10 rounded-xl border border-slate-200 px-4 text-xs font-bold text-slate-600 disabled:cursor-not-allowed disabled:text-slate-300" type="button" data-location-page="${page}" ${disabled ? 'disabled' : ''}>${label}</button>`;

    const renderPagination = pageData => {
        pagination.classList.toggle('hidden', pageData.last_page <= 1);
        pagination.innerHTML = `<span class="text-slate-400">Showing ${pageData.from}-${pageData.to} of ${pageData.total}</span><div class="flex items-center gap-2">${paginationButton('Previous', pageData.current_page - 1, pageData.current_page <= 1)}<span class="px-2 font-bold text-slate-500">${pageData.current_page} / ${pageData.last_page}</span>${paginationButton('Next', pageData.current_page + 1, pageData.current_page >= pageData.last_page)}</div>`;
    };

    const renderLocations = data => {
        locations = data.locations;
        generalLocations = data.general_locations || [];
        currentPage = data.pagination.current_page;
        count.textContent = `${data.pagination.total} ${data.pagination.total === 1 ? 'location' : 'locations'} found`;
        clearFilters.classList.toggle('hidden', !filters.search.value && !filters.type.value && !filters.status.value);
        const hasFilters = Boolean(filters.search.value || filters.type.value || filters.status.value);
        rows.innerHTML = locations.length ? locations.map(location => {
            const configured = location.configured;
            const relationship = location.type === 'specific' && location.parent_location_name
                ? `Specific · Inside ${location.parent_location_name}`
                : 'General location';
            return `<tr class="hover:bg-[#397565]/[.025]"><td class="px-5 py-4"><strong class="block text-sm">${escapeHtml(location.name)}</strong><small class="mt-1 block text-[10px] uppercase text-[#121017]/40">${escapeHtml(relationship)}</small></td><td class="px-4 py-4 text-xs">${configured ? coordinate(location.latitude) : '-'}</td><td class="px-4 py-4 text-xs">${configured ? coordinate(location.longitude) : '-'}</td><td class="px-4 py-4 text-xs font-bold">${configured ? `${Number(location.radius).toLocaleString('en-PH', { maximumFractionDigits: 2 })} m` : '-'}</td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${configured ? 'bg-[#C6F24E]/35 text-[#397565]' : 'bg-[#FF6B2C]/10 text-[#D64A12]'}">${configured ? 'GPS ready' : 'Needs setup'}</span></td><td class="px-4 py-4 text-xs text-[#121017]/50">${escapeHtml(formatDate(location.updated_at || location.created_at))}</td><td class="px-5 py-4 text-right"><button class="min-h-10 rounded-xl border border-[#397565]/20 bg-[#397565]/5 px-4 text-xs font-black text-[#397565]" type="button" data-edit-location="${location.id}">Edit</button></td></tr>`;
        }).join('') : `<tr><td class="px-5 py-12 text-center text-sm text-[#121017]/45" colspan="7">${hasFilters ? 'No locations match the selected filters.' : 'No locations have been created.'}</td></tr>`;
        renderPagination(data.pagination);
    };

    const syncParentLocation = (selected = '') => {
        const specific = form.elements.type.value === 'specific';
        const editingId = Number(form.elements.id.value) || 0;
        parentLocationWrap.classList.toggle('hidden', !specific);
        parentLocationSelect.disabled = !specific;
        parentLocationSelect.required = specific;
        parentLocationSelect.innerHTML = '<option value="">Select the containing general location</option>' + generalLocations
            .filter(location => location.id !== editingId)
            .map(location => `<option value="${location.id}">${escapeHtml(location.name)}</option>`)
            .join('');
        if (specific) parentLocationSelect.value = String(selected || '');
    };

    const loadLocations = async () => {
        const params = Object.fromEntries(new FormData(filters));
        params.page = currentPage;
        const response = await axios.get(API, { params });
        renderLocations(response.data.data);
        const url = new URL(location.href);
        ['search', 'type', 'status'].forEach(key => params[key] ? url.searchParams.set(key, params[key]) : url.searchParams.delete(key));
        currentPage > 1 ? url.searchParams.set('page', currentPage) : url.searchParams.delete('page');
        history.replaceState(null, '', url);
    };

    const openCreate = () => {
        form.reset();
        form.elements.action.value = 'create';
        form.elements.id.value = '';
        syncParentLocation();
        document.querySelector('[data-dialog-kicker]').textContent = 'New GPS boundary';
        document.querySelector('[data-dialog-title]').textContent = 'Create Location';
        saveButton.textContent = 'Create Location';
        preview.classList.add('hidden');
        resetGps();
        setManualCoordinates(false);
        dialog.showModal();
        form.elements.name.focus();
    };

    const openEdit = location => {
        form.reset();
        form.elements.action.value = 'update';
        form.elements.id.value = location.id;
        form.elements.name.value = location.name;
        form.elements.type.value = location.type;
        syncParentLocation(location.parent_location_id);
        form.elements.latitude.value = location.latitude ?? '';
        form.elements.longitude.value = location.longitude ?? '';
        form.elements.radius.value = location.radius ?? '';
        document.querySelector('[data-dialog-kicker]').textContent = 'Update GPS boundary';
        document.querySelector('[data-dialog-title]').textContent = 'Edit Location';
        saveButton.textContent = 'Save Changes';
        resetGps();
        setManualCoordinates(false);
        renderRangePreview();
        dialog.showModal();
        form.elements.name.focus();
    };

    const closeDialog = () => { stopGps(); dialog.close(); };

    document.querySelector('[data-open-location]').addEventListener('click', openCreate);
    document.querySelectorAll('[data-close-location]').forEach(button => button.addEventListener('click', closeDialog));
    dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(); });
    rows.addEventListener('click', event => {
        const button = event.target.closest('[data-edit-location]');
        if (!button) return;
        const location = locations.find(item => item.id === Number(button.dataset.editLocation));
        if (location) openEdit(location);
    });
    filters.addEventListener('submit', async event => {
        event.preventDefault();
        currentPage = 1;
        const submitButton = event.submitter;
        window.Notifications?.setLoading(submitButton, true, 'Filtering...');
        try {
            await loadLocations();
        } catch (error) {
            notify('error', error.response?.data?.message || 'Unable to filter locations.');
        } finally {
            window.Notifications?.setLoading(submitButton, false);
        }
    });
    clearFilters.addEventListener('click', () => {
        filters.reset();
        currentPage = 1;
        loadLocations().catch(error => notify('error', error.response?.data?.message || 'Unable to load locations.'));
    });
    pagination.addEventListener('click', event => {
        const button = event.target.closest('[data-location-page]');
        if (!button || button.disabled) return;
        currentPage = Number(button.dataset.locationPage);
        loadLocations().catch(error => notify('error', error.response?.data?.message || 'Unable to load locations.'));
    });
    gpsButton.addEventListener('click', toggleGps);
    refreshGpsButton.addEventListener('click', refreshGps);
    applyButton.addEventListener('click', () => {
        if (!currentPosition) return;
        form.elements.latitude.value = coordinate(currentPosition.latitude);
        form.elements.longitude.value = coordinate(currentPosition.longitude);
        renderRangePreview();
        notify('success', 'The current live coordinates were applied.');
    });
    const setManualCoordinates = enabled => {
        manualCoordinates = enabled;
        form.elements.latitude.readOnly = !enabled;
        form.elements.longitude.readOnly = !enabled;
        for (const input of [form.elements.latitude, form.elements.longitude]) {
            input.classList.toggle('bg-white', enabled);
            input.classList.toggle('bg-[#F3F0E9]/45', !enabled);
        }
        manualButton.textContent = enabled ? 'Use device GPS instead' : 'Enter coordinates manually';
        if (enabled) {
            resetGps();
            gpsMessage.textContent = 'Manual coordinates enabled. Use the latitude and longitude of your test location.';
        }
    };
    manualButton.addEventListener('click', () => {
        setManualCoordinates(!manualCoordinates);
        if (manualCoordinates) form.elements.latitude.focus();
        renderRangePreview();
    });
    form.elements.latitude.addEventListener('input', renderRangePreview);
    form.elements.longitude.addEventListener('input', renderRangePreview);
    form.elements.radius.addEventListener('input', renderRangePreview);
    form.elements.type.addEventListener('change', () => syncParentLocation());

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!form.reportValidity()) return;
        saveButton.disabled = true;
        const originalText = saveButton.textContent;
        saveButton.textContent = form.elements.action.value === 'update' ? 'Saving...' : 'Creating...';
        try {
            const response = await axios.post(API, Object.fromEntries(new FormData(form)), { headers: { 'X-CSRF-Token': csrfToken } });
            closeDialog();
            notify('success', response.data.message);
            await loadLocations().catch(() => notify('warning', 'Saved, but the location list could not refresh. Reload the page.'));
        } catch (error) {
            notify('error', error.response?.data?.message || 'Unable to save the location.');
        } finally {
            saveButton.disabled = false;
            saveButton.textContent = originalText;
        }
    });

    window.addEventListener('beforeunload', stopGps);
    const initialQuery = new URLSearchParams(location.search);
    filters.search.value = initialQuery.get('search') || '';
    filters.type.value = initialQuery.get('type') || '';
    filters.status.value = initialQuery.get('status') || '';
    loadLocations().catch(error => notify('error', error.response?.data?.message || error.message));
});
