const searchQuery = document.getElementById('searchQuery');
const searchPreview = document.getElementById('searchPreview');
const searchField = document.getElementById('searchField');

searchQuery.addEventListener('input', () => {
  const query = searchQuery.value.trim();
  const field = searchField.value;

  if (query.length < 2) {
    searchPreview.style.display = 'none';
    return;
  }

  fetch(`search_suggestions.php?field=${field}&query=${encodeURIComponent(query)}`)
    .then(res => res.json())
    .then(data => {
      // Clear previous results
      searchPreview.innerHTML = '';

      if (!Array.isArray(data) || data.length === 0) {
        const noDiv = document.createElement('div');
        noDiv.textContent = 'No matches found';
        searchPreview.appendChild(noDiv);
      } else {
        data.forEach(item => {
          const div = document.createElement('div');
          // Use textContent to avoid HTML injection and preserve long text
          div.textContent = item;
          // Attach event listener instead of inline onclick to avoid issues with quotes
          div.addEventListener('click', () => selectSuggestion(item));
          searchPreview.appendChild(div);
        });
      }

      searchPreview.style.display = 'block';
    })
    .catch(err => console.error('Error:', err));
});

function selectSuggestion(value) {
  searchQuery.value = value;
  searchPreview.style.display = 'none';

  const form = document.getElementById('homeSearchForm');
  if (form) {
    // Submit the form; it is a GET form targeting Searchpage.php
    form.submit();
  } else {
    // Fallback: navigate directly with query parameters
    const params = new URLSearchParams({ field: searchField.value, query: value });
    window.location.href = `Searchpage.php?${params.toString()}`;
  }
}

document.addEventListener('click', (e) => {
  if (!searchPreview.contains(e.target) && e.target !== searchQuery) {
    searchPreview.style.display = 'none';
  }
});