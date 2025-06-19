// --- Utility Functions ---
async function fetchAndSet(url, targetId) {
  try {
    const response = await fetch(url);
    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

    const text = await response.text();
    const target = document.getElementById(targetId);
    if (target) target.value = text;
  } catch (error) {
    console.error(`Failed to update #${targetId}:`, error);
  }
}

function enableCopyOnClick(targetId) {
  const el = document.getElementById(targetId);
  if (el) {
    el.addEventListener("click", function () {
      el.select();
      el.setSelectionRange(0, 99999);
      navigator.clipboard.writeText(el.value)
        .then(() => {
          el.style.backgroundColor = "#e0ffe0";
          setTimeout(() => el.style.backgroundColor = "", 500);
        })
        .catch(err => console.error("Copy failed:", err));
    });
  }
}

// --- Initialization ---
document.addEventListener("DOMContentLoaded", function () {
  fetchAndSet("/uuidcontentuidgen/UUID", "UUID");
  fetchAndSet("/uuidcontentuidgen/ContentUID", "ContentUID");

  document.getElementById("fetchUUID")?.addEventListener("click", () => fetchAndSet("/uuidcontentuidgen/UUID", "UUID"));
  document.getElementById("fetchContentUID")?.addEventListener("click", () => fetchAndSet("/uuidcontentuidgen/ContentUID", "ContentUID"));

  enableCopyOnClick("UUID");
  enableCopyOnClick("ContentUID");

  const displayDiv = document.getElementById('displayDiv');
  const searchDiv = document.getElementById('searchDiv');

  const displayHTML = sessionStorage.getItem('displayDiv');
  const searchHTML = sessionStorage.getItem('searchDiv');

  if (displayHTML) {
    displayDiv.innerHTML = displayHTML;

    // Re-apply textarea/input autosizing
    const textareas = displayDiv.querySelectorAll('textarea');
    textareas.forEach(el => {
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    });

    const versionInputs = displayDiv.querySelectorAll('input.version');
    versionInputs.forEach(el => {
      const tmp = document.createElement('span');
      tmp.style.visibility = 'hidden';
      tmp.style.position = 'absolute';
      tmp.style.whiteSpace = 'pre';
      tmp.style.font = getComputedStyle(el).font;
      tmp.textContent = el.value || el.placeholder || '';
      document.body.appendChild(tmp);
      const textWidth = tmp.offsetWidth;
      el.style.width = (textWidth + 44) + 'px';
      document.body.removeChild(tmp);
    });
  }

  if (searchHTML) {
    searchDiv.innerHTML = searchHTML;
  }
});

// --- Core Actions ---
function search(searchId) {
  const inputValue = document.getElementById(searchId).value;
  const pathValue = document.getElementById('path').value;
  const url = `/search/${pathValue}/${inputValue}`;

  fetch(url)
    .then(res => res.ok ? res.text() : Promise.reject(`HTTP error! Status: ${res.status}`))
    .then(data => document.getElementById('searchDiv').innerHTML = data)
    .catch(err => document.getElementById('searchDiv').innerHTML = 'Error: ' + err);
}

function replace(searchId, replaceId) {
  const searchValue = document.getElementById(searchId).value;
  const replaceValue = document.getElementById(replaceId).value;
  const pathValue = document.getElementById('path').value;
  const url = `/replace/${pathValue}/${searchValue}/${replaceValue}`;

  fetch(url)
    .then(res => res.ok ? res.text() : Promise.reject(`HTTP error! Status: ${res.status}`))
    .then(data => {
      document.getElementById('searchDiv').innerHTML = data;
      const fileNameDiv = document.querySelector('#displayDiv #fileName');
      if (fileNameDiv) {
        const text = Array.from(fileNameDiv.childNodes)
                         .filter(n => n.nodeType === Node.TEXT_NODE)
                         .map(n => n.textContent).join('').trim();
        display(`/display/${pathValue}/${text}`);
      }
    })
    .catch(err => document.getElementById('searchDiv').innerHTML = 'Error: ' + err);
}

function display(filepath) {
  fetch(filepath)
    .then(res => res.ok ? res.text() : Promise.reject(`HTTP error! Status: ${res.status}`))
    .then(data => {
      const displayDiv = document.getElementById('displayDiv');
      displayDiv.innerHTML = data;

      displayDiv.querySelectorAll('textarea').forEach(el => {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
      });

      displayDiv.querySelectorAll('input.version').forEach(el => {
        const tmp = document.createElement('span');
        tmp.style.visibility = 'hidden';
        tmp.style.position = 'absolute';
        tmp.style.whiteSpace = 'pre';
        tmp.style.font = getComputedStyle(el).font;
        tmp.textContent = el.value || el.placeholder || '';
        document.body.appendChild(tmp);
        el.style.width = (tmp.offsetWidth + 44) + 'px';
        tmp.remove();
      });
    })
    .catch(err => document.getElementById('displayDiv').innerHTML = 'Error: ' + err);
}

document.addEventListener('input', e => {
  if (e.target.tagName.toLowerCase() === 'textarea') {
    e.target.style.height = 'auto';
    e.target.style.height = e.target.scrollHeight + 'px';
  }
});

// --- Form Actions ---
function submitMainForm() {
  const form = document.getElementById('mainForm');
  const formData = new FormData(form);
  if (!form.querySelector('.langline')) {
    const xmlContent = document.getElementById('data').textContent.trim();
    formData.append('data', xmlContent);
  }
  fetch("/save/", { method: "POST", body: formData })
    .then(res => res.text())
    .then(() => {
      const nameDiv = document.getElementById("fileName");
      nameDiv.classList.add('flash-green');
      setTimeout(() => nameDiv.classList.remove('flash-green'), 1000);
    })
    .catch(err => {
      const nameDiv = document.getElementById("fileName");
      nameDiv.classList.add('flash-red');
      nameDiv.innerHTML += ` Error: ${err}`;
      setTimeout(() => nameDiv.classList.remove('flash-red'), 1000);
    });
}

function addRowToForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return console.error('Form not found');

  const inputValue = +document.getElementsByName('nextKey')[0].value;
  document.getElementsByName('nextKey')[0].value = inputValue + 1;

  const div = document.createElement('div');
  div.className = 'langline';
  div.id = inputValue;

  div.innerHTML = `
    <input type="text" name="data[content][${inputValue}][@attributes][contentuid]" placeholder="contentuid" class="contentuid">
    <input type="text" name="data[content][${inputValue}][@attributes][version]" placeholder="1" class="version" style="width: 53px;">
    <textarea name="data[content][${inputValue}][@value]" placeholder="Text to Display"></textarea>
    <button class="rmDiv" onclick="removeDivById('${inputValue}')">X</button>
  `;

  form.insertBefore(div, form.firstChild);
}

function removeDivById(divId) {
  document.getElementById(divId)?.remove();
}

function clearInput(inputId) {
  const input = document.getElementById(inputId);
  if (input) input.value = '';
}

document.getElementById('search')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    search('search');
  }
});

document.getElementById('replace')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') {
    e.preventDefault();
    replace('search', 'replace');
  }
});

window.addEventListener('beforeunload', () => {
  sessionStorage.setItem('displayDiv', document.getElementById('displayDiv')?.innerHTML || '');
  sessionStorage.setItem('searchDiv', document.getElementById('searchDiv')?.innerHTML || '');
});
