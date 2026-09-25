(() => {
  'use strict';

  const SIZE = 360;
  const OUTPUT_SIZE = 512;
  const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  let active = false;

  async function open(file) {
    if (active) throw new Error('Finish the current photo crop first.');
    if (!TYPES.includes(file?.type)) throw new Error('Choose a JPG, PNG, or WebP image.');
    if (file.size > 5 * 1024 * 1024) throw new Error('Choose a profile picture no larger than 5 MB.');

    active = true;
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.src = url;
    try { await image.decode(); }
    catch (error) { URL.revokeObjectURL(url); active = false; throw new Error('This photo could not be opened. Choose another image.'); }

    const dialog = document.createElement('dialog');
    dialog.className = 'profile-crop-dialog';
    dialog.setAttribute('aria-labelledby', 'profile-crop-title');
    dialog.innerHTML = `<div class="profile-crop-panel">
      <header class="profile-crop-header"><div><h2 id="profile-crop-title">Fit your profile photo</h2><p>Drag to position your photo inside the circle.</p></div><button class="profile-crop-close" type="button" data-crop-cancel aria-label="Cancel photo crop">×</button></header>
      <div class="profile-crop-body"><canvas class="profile-crop-preview" width="${SIZE}" height="${SIZE}" tabindex="0" role="img" aria-label="Profile photo crop preview. Drag or use arrow keys to reposition."></canvas>
        <label class="profile-crop-zoom">Zoom <input type="range" min="100" max="300" step="5" value="100" data-crop-zoom aria-label="Photo zoom"><span data-crop-zoom-value>100%</span></label>
        <p class="profile-crop-hint">The area in the circle will be saved as your profile picture.</p></div>
      <footer class="profile-crop-actions"><button type="button" data-crop-cancel>Cancel</button><button type="button" data-crop-save>Use photo</button></footer>
    </div>`;
    document.body.append(dialog);
    const canvas = dialog.querySelector('canvas');
    const context = canvas.getContext('2d');
    const zoomInput = dialog.querySelector('[data-crop-zoom]');
    const baseScale = Math.max(SIZE / image.naturalWidth, SIZE / image.naturalHeight);
    let scale = baseScale;
    let left = (SIZE - image.naturalWidth * scale) / 2;
    let top = (SIZE - image.naturalHeight * scale) / 2;
    let lastPointer = null;
    const clamp = () => {
      left = Math.min(0, Math.max(SIZE - image.naturalWidth * scale, left));
      top = Math.min(0, Math.max(SIZE - image.naturalHeight * scale, top));
    };
    const draw = () => {
      context.clearRect(0, 0, SIZE, SIZE);
      context.drawImage(image, left, top, image.naturalWidth * scale, image.naturalHeight * scale);
    };
    zoomInput.oninput = () => {
      const previous = scale;
      scale = baseScale * Number(zoomInput.value) / 100;
      left = SIZE / 2 - (SIZE / 2 - left) * scale / previous;
      top = SIZE / 2 - (SIZE / 2 - top) * scale / previous;
      clamp(); draw();
      dialog.querySelector('[data-crop-zoom-value]').textContent = `${zoomInput.value}%`;
    };
    canvas.onpointerdown = event => {
      lastPointer = {x:event.clientX, y:event.clientY};
      canvas.setPointerCapture(event.pointerId);
      canvas.classList.add('is-dragging');
    };
    canvas.onpointermove = event => {
      if (!lastPointer) return;
      const factor = SIZE / canvas.getBoundingClientRect().width;
      left += (event.clientX - lastPointer.x) * factor;
      top += (event.clientY - lastPointer.y) * factor;
      lastPointer = {x:event.clientX, y:event.clientY};
      clamp(); draw();
    };
    const stopDrag = () => { lastPointer = null; canvas.classList.remove('is-dragging'); };
    canvas.onpointerup = stopDrag;
    canvas.onpointercancel = stopDrag;
    canvas.onkeydown = event => {
      const moves = {ArrowLeft:[-8,0], ArrowRight:[8,0], ArrowUp:[0,-8], ArrowDown:[0,8]};
      if (!moves[event.key]) return;
      event.preventDefault();
      left += moves[event.key][0]; top += moves[event.key][1];
      clamp(); draw();
    };
    draw();

    return new Promise((resolve, reject) => {
      let result = null;
      dialog.addEventListener('close', () => {
        URL.revokeObjectURL(url);
        dialog.remove();
        active = false;
        resolve(result);
      }, {once:true});
      dialog.querySelectorAll('[data-crop-cancel]').forEach(button => button.onclick = () => dialog.close());
      dialog.querySelector('[data-crop-save]').onclick = () => {
        const save = dialog.querySelector('[data-crop-save]');
        save.disabled = true;
        save.textContent = 'Preparing…';
        const output = document.createElement('canvas');
        output.width = OUTPUT_SIZE; output.height = OUTPUT_SIZE;
        const outputContext = output.getContext('2d');
        outputContext.fillStyle = '#fff';
        outputContext.fillRect(0, 0, OUTPUT_SIZE, OUTPUT_SIZE);
        const ratio = OUTPUT_SIZE / SIZE;
        outputContext.drawImage(image, left * ratio, top * ratio, image.naturalWidth * scale * ratio, image.naturalHeight * scale * ratio);
        output.toBlob(blob => {
          if (!dialog.open) return;
          if (!blob) { save.disabled = false; save.textContent = 'Use photo'; return; }
          const cropped = new File([blob], 'profile-photo.jpg', {type:'image/jpeg', lastModified:Date.now()});
          result = cropped;
          dialog.close();
        }, 'image/jpeg', .9);
      };
      try { dialog.showModal(); }
      catch (error) {
        URL.revokeObjectURL(url);
        dialog.remove();
        active = false;
        reject(new Error('The photo editor could not open. Try again.'));
      }
    });
  }

  window.ProfilePhotoCrop = {open};
})();
