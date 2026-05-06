const API_URL = 'index.php?route=/api/user';
let userModal;
let currentPage = 0;
const limit = 10;

document.addEventListener('DOMContentLoaded', () => {
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
    loadUsers();
});

// --- Fetch Data ---
async function loadUsers(query = '', start = 0) { 
    const url = `${API_URL}&q=${encodeURIComponent(query)}&start=${start}&limit=${limit}`;
    
    try {
        const response = await fetch(url);
        const users = await response.json();
        renderTable(users);
        renderPagination(query, start);
    } catch (err) {
        console.error("Failed to load users", err);
    }
}

// --- Render UI ---
function renderTable(users) {
    const tbody = document.getElementById('userTableBody');
    tbody.innerHTML = users.map(user => `
        <tr>
            <td>${user.id}</td> 
            <td>${user.name}</td>
            <td>${user.age}</td>
            <td>${user.email}</td>
            <td>
                <button class="btn btn-warning btn-sm" onclick="editUser(${user.id})">Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})">Remove</button>
            </td>
        </tr>
    `).join('');
}

// --- Search ---
let timeout = null;
function searchUsers() {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        const q = document.getElementById('searchInput').value;
        currentPage = 0;
        loadUsers(q, 0);
    }, 500);
}

// --- Add/Edit Logic ---
function openAddModal() {
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = '';
    document.getElementById('modalTitle').innerText = 'Add New User';
    userModal.show();
}

async function editUser(id) {
    const response = await fetch(`${API_URL}/${id}`);
    const user = await response.json();
    
    document.getElementById('userId').value = user.id;
    document.getElementById('userName').value = user.name;
    document.getElementById('userAge').value = user.age;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userAvatar').value = user.avatarUrl;
    
    document.getElementById('modalTitle').innerText = 'Edit User';
    userModal.show();
}

async function saveUser() {
    const id = document.getElementById('userId').value;
    const data = {
        name: document.getElementById('userName').value,
        age: document.getElementById('userAge').value,
        email: document.getElementById('userEmail').value,
        avatarUrl: document.getElementById('userAvatar').value
    };

    const method = id ? 'PUT' : 'POST';
    const url = id ? `${API_URL}/${id}` : API_URL;

    const response = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });

    if (response.ok) {
        userModal.hide();
        loadUsers(document.getElementById('searchInput').value, currentPage * limit);
    } else {
        const err = await response.json();
        alert(err.error || "Something went wrong");
    }
}

// --- Delete Logic ---
async function deleteUser(id) {
    if (confirm("Are you sure you want to remove this user?")) {
        const response = await fetch(`${API_URL}/${id}`, { method: 'DELETE' });
        if (response.ok) {
            loadUsers(document.getElementById('searchInput').value, currentPage * limit);
        } else {
            alert("Delete failed.");
        }
    }
}

// --- Basic Pagination UI ---
function renderPagination(query, currentStart) {
    const pag = document.getElementById('pagination');
    currentPage = currentStart / limit;
    
    pag.innerHTML = `
        <li class="page-item ${currentPage === 0 ? 'disabled' : ''}">
            <a class="page-link" href="#" onclick="loadUsers('${query}', ${(currentPage - 1) * limit})">Previous</a>
        </li>
        <li class="page-item active"><a class="page-link">${currentPage + 1}</a></li>
        <li class="page-item">
            <a class="page-link" href="#" onclick="loadUsers('${query}', ${(currentPage + 1) * limit})">Next</a>
        </li>
    `;
}