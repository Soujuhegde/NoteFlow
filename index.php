<?php
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $notesFile = 'notes.json';
    if (!file_exists($notesFile)) file_put_contents($notesFile, json_encode([]));
    $notes = json_decode(file_get_contents($notesFile), true);
    $action = $_POST['action'];
    if ($action === 'save') {
        $id = $_POST['id'];
        $content = $_POST['content'];
        $owner = $_POST['owner'];
        if ($content === '' && $owner === '') {
            unset($notes[$id]);
        } else {
            $notes[$id] = ['owner' => $owner, 'content' => $content];
        }
        file_put_contents($notesFile, json_encode($notes));
        echo json_encode(['status' => 'saved']);
        exit;
    }
    if ($action === 'get') {
        $id = $_POST['id'];
        echo json_encode($notes[$id] ?? null);
        exit;
    }
    if ($action === 'get_all') {
        echo json_encode($notes);
        exit;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>NoteFlow - Secure Note Editor</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
<style>
body.dark-mode { background: linear-gradient(to bottom, #23252a, #2b2d31); color: #e0e0e0; }
#secret-id { transition: background-color 0.3s, color 0.3s; background-color: #2b2d31; color: #f0f0f0; border-color: #555; }
.editor-textarea { background-color: #2b2d31; color: #f0f0f0; border: none; outline: none; resize: none; transition: background-color 0.3s, color 0.3s; }
.status-saved { color: #4ade80; }
.status-saving { color: #fbbf24; }
.status-error { color: #ef4444; }
#save-status { transition: opacity 0.5s ease-in-out; }
.note-actions { display: inline-flex; gap: 5px; margin-left: 10px; opacity: 0; transition: opacity 0.2s; }
li:hover .note-actions { opacity: 1; }
.glow { box-shadow: 0 0 20px rgba(99, 102, 241, 0.8), 0 0 40px rgba(99, 102, 241, 0.6); animation: pulseGlow 3s infinite; }
@keyframes pulseGlow { 0%, 100% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.8), 0 0 40px rgba(99, 102, 241, 0.6); } 50% { box-shadow: 0 0 30px rgba(99, 102, 241, 1), 0 0 60px rgba(99, 102, 241, 0.9); } }
.flex-col-scroll { display: flex; flex-direction: column; overflow-y: auto; }
</style>
</head>
<body class="dark-mode font-sans">

<div id="landing" class="flex flex-col items-center justify-center min-h-screen px-4 py-8">
  <div class="bg-gray-800 bg-opacity-90 backdrop-blur-md rounded-2xl shadow-xl p-8 max-w-3xl w-full text-center border border-gray-700 transition-all">
    <div class="mb-6 flex justify-center">
   <img src="/img/NoteFlow-Logo.png" alt="NoteFlow Logo" class="w-48 h-48 object-contain rounded-xl glow" />
    </div>
    <h1 class="text-3xl font-bold mb-2">Welcome to NoteFlow</h1>
    <p class="text-gray-300 mb-6">Your safe space for ideas and notes. Start a new secret page or open an existing one.</p>
    <form id="access-form" class="space-y-4">
      <div>
        <label for="secret-id" class="block text-sm font-medium mb-2">Enter Your Secret Page ID</label>
        <input type="text" id="secret-id" class="w-full px-4 py-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., my-secret-note" required pattern="[A-Za-z0-9_-]+" title="Alphanumeric, underscore, and dash only" autocomplete="off" />
      </div>
      <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition duration-300 ease-in-out shadow-lg hover:shadow-xl">
        <i class="fas fa-arrow-right mr-2"></i>Go to Note
      </button>
    </form>
  </div>
</div>
<div id="editor" class="hidden flex flex-col min-h-screen">
  <div class="bg-gray-800 text-white px-4 py-3 flex items-center justify-between shadow-md">
    <div class="flex items-center space-x-3">
      <i class="fas fa-edit text-xl text-indigo-400"></i>
      <h2 id="note-title" class="text-lg font-semibold">Note: <span id="current-id"></span></h2>
      <!-- Removed reload and back buttons as requested -->
    </div>
    <div class="flex items-center space-x-4">
      <div id="save-status" class="text-sm"><i class="fas fa-check"></i> Saved</div>
      <button id="undo-btn" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md transition duration-200 hidden" title="Undo Delete">
        <i class="fas fa-undo mr-1"></i>Undo Delete
      </button>
    </div>
  </div>
  <div class="flex-1 flex">
    <div class="w-1/4 bg-gray-900 p-4 border-r border-gray-700 transition-all flex-col-scroll" id="recent-panel">
      <h3 class="text-lg font-semibold mb-4 text-indigo-400"><i class="fas fa-history mr-2"></i>Recent Notes</h3>
      <ul id="recent-notes" class="space-y-2"></ul>
    </div>
    <div class="flex-1 p-4">
      <textarea id="note-content" class="editor-textarea w-full h-full p-4 rounded-lg shadow-inner text-lg font-mono" placeholder="Start writing your secret notes here... Auto-save is active." spellcheck="false" autocomplete="off" autocorrect="off" autocapitalize="off"></textarea>
    </div>
  </div>
</div>
<script>
function setAdmin(id) {
    localStorage.setItem('noteflow-admin-' + id, 'true');
}
function isAdmin(id) {
    return localStorage.getItem('noteflow-admin-' + id) === 'true';
}
function clearAdmin(id) {
    localStorage.removeItem('noteflow-admin-' + id);
}
function getAdminNotes(allNotes) {
    return Object.keys(allNotes)
        .filter(id => isAdmin(id) && allNotes[id].owner === 'admin')
        .reduce((obj, id) => { obj[id] = allNotes[id]; return obj; }, {});
}
const landing = document.getElementById('landing');
const editor = document.getElementById('editor');
const recentPanel = document.getElementById('recent-panel');
const accessForm = document.getElementById('access-form');
const secretIdInput = document.getElementById('secret-id');
const noteContent = document.getElementById('note-content');
const currentId = document.getElementById('current-id');
const saveStatus = document.getElementById('save-status');
const recentNotes = document.getElementById('recent-notes');
const undoBtn = document.getElementById('undo-btn');
let currentNoteId = '';
let editable = true;
let timeoutId = null;
let lastDeletedNote = null;
let isReloading = false;

// AJAX helper
async function ajax(action, data = {}) {
    data.action = action;
    const res = await fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(data)
    });
    return res.json();
}

// Load note by ID and set editable state
async function loadNoteById(id, pushState = true) {
    if (!id) return showLanding();
    currentNoteId = id;
    saveStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    let existingNote = await ajax('get', {id});
    if (!existingNote) {
        // New note; save empty content owned by admin
        await ajax('save', {id, content: '', owner: 'admin'});
        setAdmin(id);
        editable = true;
        noteContent.value = '';
    } else {
        editable = isAdmin(id) && existingNote.owner === "admin";
        noteContent.value = existingNote.content;
    }
    noteContent.disabled = !editable;
    currentId.textContent = id;
    landing.classList.add('hidden');
    editor.classList.remove('hidden');
    if (editable) {
        recentPanel.style.display = "block";
        undoBtn.style.display = "";
        await updateRecentNotes();
    } else {
        recentPanel.style.display = "none";
        undoBtn.style.display = "none";
    }
    saveStatus.innerHTML = '<i class="fas fa-check"></i> Saved';
    document.title = "NoteFlow - " + id;
    if (pushState) {
        history.pushState({id}, "", "?id=" + encodeURIComponent(id));
    }
    noteContent.focus();
    isReloading = false;
}

// Show landing page and reset editor
function showLanding(pushState = true) {
    currentNoteId = '';
    editable = false;
    noteContent.value = '';
    noteContent.disabled = true;
    currentId.textContent = '';
    landing.classList.remove('hidden');
    editor.classList.add('hidden');
    recentPanel.style.display = "none";
    undoBtn.style.display = "none";
    saveStatus.innerHTML = '';
    document.title = "NoteFlow - Secure Note Editor";
    if (pushState) {
        history.pushState({}, "", location.pathname);
    }
    noteContent.blur();
}

// Auto-save on input
noteContent.addEventListener('input', () => {
    if (!editable) return;
    saveStatus.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    if (timeoutId) clearTimeout(timeoutId);
    timeoutId = setTimeout(saveNote, 1000);
});

async function saveNote() {
    if (!editable) return;
    await ajax('save', {id: currentNoteId, content: noteContent.value, owner: 'admin'});
    saveStatus.innerHTML = '<i class="fas fa-check"></i> Saved';
    await updateRecentNotes();
}

// Update recent notes list for admin
async function updateRecentNotes() {
    if (!editable) {
        recentNotes.innerHTML = '';
        return;
    }
    const allNotes = await ajax('get_all');
    const adminNotes = getAdminNotes(allNotes);
    recentNotes.innerHTML = '';
    Object.keys(adminNotes).reverse().forEach(id => {
        const li = document.createElement('li');
        li.className = 'p-2 bg-gray-800 rounded hover:bg-gray-700 flex justify-between items-center cursor-pointer transition-all';
        const span = document.createElement('span');
        span.textContent = id;
        span.addEventListener('click', async () => {
            await loadNoteById(id);
        });
        const actions = document.createElement('span');
        actions.className = 'note-actions';
        // Rename button
        const renameBtn = document.createElement('button');
        renameBtn.className = 'bg-yellow-400 hover:bg-yellow-500 text-white px-2 py-1 rounded text-xs';
        renameBtn.textContent = 'Rename';
        renameBtn.addEventListener('click', async () => {
            const newName = prompt('Enter new name for the note:', id);
            if (newName && /^[A-Za-z0-9_-]+$/.test(newName)) {
                const noteData = await ajax('get', {id});
                await ajax('save', {id: newName, content: noteData.content, owner: 'admin'});
                await ajax('save', {id, content: '', owner: ''}); // delete old note
                clearAdmin(id);
                setAdmin(newName);
                if (currentNoteId === id) {
                    currentNoteId = newName;
                }
                currentId.textContent = currentNoteId;
                document.title = "NoteFlow - " + currentNoteId;
                updateRecentNotes();
                history.replaceState({id: currentNoteId}, "", "?id=" + encodeURIComponent(currentNoteId));
            }
        });
        // Delete button
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded text-xs';
        deleteBtn.textContent = 'Delete';
        deleteBtn.addEventListener('click', async () => {
            if (confirm(`Delete note "${id}"?`)) {
                const noteData = await ajax('get', {id});
                lastDeletedNote = {id, content: noteData.content};
                await ajax('save', {id, content: '', owner: ''});
                clearAdmin(id);
                updateRecentNotes();
                undoBtn.classList.remove('hidden');
                // If current note deleted, keep editor open with empty content and editable set false
                if (currentNoteId === id) {
                    editable = false;
                    noteContent.disabled = true;
                    noteContent.value = '';
                    saveStatus.innerHTML = '<i class="fas fa-exclamation-circle"></i> Note deleted';
                    // Keep showing editor with blank content (not redirect to landing)
                }
            }
        });
        actions.appendChild(renameBtn);
        actions.appendChild(deleteBtn);
        li.appendChild(span);
        li.appendChild(actions);
        recentNotes.appendChild(li);
    });
}

// Undo delete
undoBtn.addEventListener('click', async () => {
    if (lastDeletedNote) {
        await ajax('save', {id: lastDeletedNote.id, content: lastDeletedNote.content, owner: 'admin'});
        setAdmin(lastDeletedNote.id);
        if (currentNoteId === lastDeletedNote.id) {
            editable = true;
            noteContent.disabled = false;
            noteContent.value = lastDeletedNote.content;
            saveStatus.innerHTML = '<i class="fas fa-check"></i> Restored';
            await updateRecentNotes();
        } else {
            await loadNoteById(lastDeletedNote.id);
        }
        undoBtn.classList.add('hidden');
        lastDeletedNote = null;
    }
});

// Access form submit loads note
accessForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = secretIdInput.value.trim();
    if (!id || !/^[A-Za-z0-9_-]+$/.test(id)) return;
    await loadNoteById(id);
});

// Handle browser back/forward navigation: always show landing page on back
window.addEventListener('popstate', async (event) => {
    showLanding(false);
});

// On page load, load note or landing
window.addEventListener('load', async () => {
    const params = new URLSearchParams(window.location.search);
    const id = params.get('id');
    if (id) {
        await loadNoteById(id, false);
    } else {
        showLanding(false);
    }
});
</script>
</body>
</html>
