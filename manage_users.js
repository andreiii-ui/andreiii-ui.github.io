  async function fetchUsers(){
    const res = await fetch('fetch_users.php');
    const data = await res.json();
    const tbody = document.querySelector('#usersTable tbody');
    tbody.innerHTML = '';
    data.forEach(u=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${u.id}</td><td>${escapeHtml(u.username)}</td><td>${u.role}</td><td>
        <button data-id="${u.id}" class="editBtn">Edit</button>
        <button data-id="${u.id}" class="delBtn">Delete</button></td>`;
      tbody.appendChild(tr);
    });
    document.querySelectorAll('.editBtn').forEach(b=>b.onclick=onEdit);
    document.querySelectorAll('.delBtn').forEach(b=>b.onclick=onDelete);
  }

  function escapeHtml(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  document.getElementById('openCreate').onclick = ()=>{
    document.getElementById('modalTitle').textContent='Create User';
    document.getElementById('userId').value='';
    document.getElementById('username').value='';
    document.getElementById('password').value='';
    document.getElementById('roleSelect').value='viewer';
    document.getElementById('userModal').style.display='block';
  };

  document.getElementById('cancelBtn').onclick = ()=>{ document.getElementById('userModal').style.display='none'; };

  async function onEdit(e){
    const id = e.target.dataset.id;
    const res = await fetch('fetch_users.php?id='+id);
    const u = (await res.json())[0];
    document.getElementById('modalTitle').textContent='Edit User';
    document.getElementById('userId').value=u.id;
    document.getElementById('username').value=u.username;
    document.getElementById('password').value='';
    document.getElementById('roleSelect').value=u.role;
    document.getElementById('userModal').style.display='block';
  }

  async function onDelete(e){
    if(!confirm('Delete this user?')) return;
    const id=e.target.dataset.id;
    const res = await fetch('delete_user.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+encodeURIComponent(id)});
    const data = await res.json();
    alert(data.message);
    fetchUsers();
  }

  document.getElementById('userForm').onsubmit = async function(ev){
    ev.preventDefault();
    const form = new FormData(this);
    const id = form.get('id');
    const url = id? 'update_user.php' : 'create_user.php';
    const opts = { method:'POST', body: new URLSearchParams(Object.fromEntries(form.entries())) };
    const res = await fetch(url, opts);
    const data = await res.json();
    alert(data.message);
    if(data.success){ document.getElementById('userModal').style.display='none'; fetchUsers(); }
  };

  // initial load
  fetchUsers();