// ---- Image gallery on product detail ----
document.querySelectorAll('.gallery-thumb').forEach(thumb => {
  thumb.addEventListener('click', function () {
    const mainImg = document.querySelector('.gallery-main img');
    if (mainImg) mainImg.src = this.dataset.src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
  });
});

// ---- Qty stepper ----
document.querySelectorAll('.qty-stepper').forEach(stepper => {
  const input = stepper.querySelector('input');
  stepper.querySelector('.qty-minus')?.addEventListener('click', () => {
    const min = parseInt(input.min) || 1;
    if (parseInt(input.value) > min) input.value = parseInt(input.value) - 1;
  });
  stepper.querySelector('.qty-plus')?.addEventListener('click', () => {
    const max = parseInt(input.max) || 99;
    if (parseInt(input.value) < max) input.value = parseInt(input.value) + 1;
  });
});

// ---- Tabs ----
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    const panel = this.dataset.tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    this.classList.add('active');
    document.getElementById(panel)?.classList.add('active');
  });
});
// Activate first tab by default
document.querySelector('.tab-btn')?.click();

// ---- Image upload preview ----
const imgInput = document.getElementById('product-images');
if (imgInput) {
  imgInput.addEventListener('change', function () {
    const strip = document.getElementById('img-preview-strip');
    strip.innerHTML = '';
    Array.from(this.files).slice(0, 5).forEach(file => {
      const reader = new FileReader();
      reader.onload = e => {
        const div = document.createElement('div');
        div.className = 'img-preview-item';
        div.innerHTML = `<img src="${e.target.result}" alt="">`;
        strip.appendChild(div);
      };
      reader.readAsDataURL(file);
    });
  });
}

// ---- Confirm deletes ----
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function (e) {
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});
