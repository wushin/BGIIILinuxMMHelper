async function UUIDgen(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const text = await response.text();
        const textarea = document.getElementById("UUID");
        if (textarea) {
            textarea.value = text;
        }
    } catch (error) {
        console.error("Failed to update textarea:", error);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    UUIDgen("/uuidcontentuidgen/UUID");
});
document.getElementById("fetchUUID").addEventListener("click", function () {
    UUIDgen("/uuidcontentuidgen/UUID");
});

document.getElementById("UUID").addEventListener("click", function () {
    const textarea = this;
    textarea.select();
    textarea.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(textarea.value)
        .then(() => {
            console.log("Copied to clipboard");
            textarea.style.backgroundColor = "#e0ffe0"; // light green
            setTimeout(() => textarea.style.backgroundColor = "", 500);
        })
        .catch(err => {
            console.error("Copy failed:", err);
        });
});

async function ContentUIDgen(url) {
    try {
        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const text = await response.text();
        const textarea = document.getElementById("ContentUID");
        if (textarea) {
            textarea.value = text;
        }
    } catch (error) {
        console.error("Failed to update textarea:", error);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    ContentUIDgen("/uuidcontentuidgen/ContentUID");
});
document.getElementById("fetchContentUID").addEventListener("click", function () {
    ContentUIDgen("/uuidcontentuidgen/ContentUID");
});
document.getElementById("ContentUID").addEventListener("click", function () {
    const textarea = this;
    textarea.select();
    textarea.setSelectionRange(0, 99999); 

    navigator.clipboard.writeText(textarea.value)
        .then(() => {
            console.log("Copied to clipboard");
            textarea.style.backgroundColor = "#e0ffe0"; // light green
            setTimeout(() => textarea.style.backgroundColor = "", 500);
        })
        .catch(err => {
            console.error("Copy failed:", err);
        });
});

function search(search) {
  const inputValue = document.getElementById(search).value;
  const pathValue = document.getElementById('path').value;
  const url = "/search/" + pathValue + "/" + inputValue ;

  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.text();
    })
    .then(data => {
      document.getElementById('searchDiv').innerHTML = data;
    })
    .catch(error => {
      document.getElementById('searchDiv').innerHTML = 'Error: ' + error.message;
    });
}

function replace(search,replace) {
  const searchValue = document.getElementById(search).value;
  const replaceValue = document.getElementById(replace).value;
  const pathValue = document.getElementById('path').value;
  const url = "/replace/" + pathValue + "/" + searchValue + "/" + replaceValue ;

  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.text();
    })
    .then(data => {
      document.getElementById('searchDiv').innerHTML = data;
      const modPath = document.getElementById('path').value;
      const fileNameDiv = document.querySelector('#displayDiv #fileName');
      if (fileNameDiv) {
        let filePath = '';
        fileNameDiv.childNodes.forEach(node => {
          if (node.nodeType === Node.TEXT_NODE) {
            filePath += node.textContent;
          }
        });
        display('/display/' + modPath + "/" + filePath.trim());
      }
    })
    .catch(error => {
      document.getElementById('searchDiv').innerHTML = 'Error: ' + error.message;
    });
}

function display(filepath) {
  const url = filepath ;

  fetch(url)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.text();
    })
    .then(data => {
      const displayDiv = document.getElementById('displayDiv');
      displayDiv.innerHTML = data;

      // Auto-resize textareas by height
      const textareas = displayDiv.querySelectorAll('textarea');
      textareas.forEach(el => {
        el.style.height = 'auto';
        el.style.height = el.scrollHeight + 'px';
      });

      // Auto-resize input.version by width
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
        el.style.width = (textWidth + 44) + 'px'; // Add padding + border width
        document.body.removeChild(tmp);
      });
    })
    .catch(error => {
      document.getElementById('displayDiv').innerHTML = 'Error: ' + error.message;
    });
}

document.addEventListener('input', event => {
  if (event.target.tagName.toLowerCase() === 'textarea') {
    event.target.style.height = 'auto';
    event.target.style.height = event.target.scrollHeight + 'px';
  }
});

function submitMainForm() {
  const form = document.getElementById('mainForm');
  const formData = new FormData(form);
  if (!form.querySelector('.langline')) {
    const xmlContent = document.getElementById('data').textContent.trim();
    formData.append('data', xmlContent);
  }
  fetch("/save/", {
    method: "POST",
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    document.getElementById("fileName").classList.add('flash-green');
    setTimeout(() => {
      document.getElementById("fileName").classList.remove('flash-green');
    }, 1000);
  })
  .catch(error => {
    document.getElementById("fileName").classList.add('flash-red');
    document.getElementById("fileName").innerHTML = document.getElementById("fileName").innerHTML + " Error: " + error;
    setTimeout(() => {
      document.getElementById("fileName").classList.remove('flash-green');
    }, 1000);
  });
}

function addRowToForm(formId) {
  const form = document.getElementById(formId);
  if (!form) {
      console.error('Form not found');
      return;
  }

  const elements = document.getElementsByName('nextKey');
  const inputElement = elements[0];
  const inputValue = inputElement.value;
  inputElement.value = Number(inputValue) + 1;

  const div = document.createElement('div');
  div.className = 'langline';
  div.id = inputValue;

  const input1 = document.createElement('input');
  input1.type = 'text';
  input1.name = 'data[content][' + inputValue + '][@attributes][contentuid]';
  input1.placeholder = 'contentuid';
  input1.className = 'contentuid';

  const input2 = document.createElement('input');
  input2.type = 'text';
  input2.name = 'data[content][' + inputValue + '][@attributes][version]';
  input2.placeholder = '1';
  input2.className = 'version';

  const textarea = document.createElement('textarea');
  textarea.name = 'data[content][' + inputValue + '][@value]';
  textarea.placeholder = 'Text to Display';

  const button = document.createElement('button');
  button.className = 'rmDiv';
  button.innerText = 'X';
  button.setAttribute('onclick', "removeDivById('" + inputValue + "')");

  div.appendChild(input1);
  div.appendChild(input2);
  div.appendChild(textarea);
  div.appendChild(button);

  form.insertBefore(div, form.firstChild);
}
function removeDivById(divId) {
    const div = document.getElementById(divId);
    if (div) {
        div.remove();
    } else {
        console.warn(`Element with id "${divId}" not found.`);
    }
}
function clearInput(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = '';
    }
}
document.getElementById('search').addEventListener('keydown', function(event) {
  if (event.key === 'Enter') {
    event.preventDefault();
    search('search');
  }
});
document.getElementById('replace').addEventListener('keydown', function(event) {
  if (event.key === 'Enter') {
    event.preventDefault();
    replace('search','replace');
  }
});

