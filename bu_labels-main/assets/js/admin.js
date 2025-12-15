// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
  // Mobile menu toggle
  const menuBtn = document.getElementById("menu-btn");
  const navLinksContainer = document.querySelector(".nav-links-container");
  const menuBtnIcon = menuBtn ? menuBtn.querySelector("i") : null;

  if (menuBtn && navLinksContainer && menuBtnIcon) {
    menuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      navLinksContainer.classList.toggle("active");
      
      const isOpen = navLinksContainer.classList.contains("active");
      menuBtnIcon.setAttribute(
        "class",
        isOpen ? "ri-close-line" : "ri-menu-3-line"
      );
    });

    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
      if (!navLinksContainer.contains(e.target) && !menuBtn.contains(e.target)) {
        navLinksContainer.classList.remove("active");
        if (menuBtnIcon) {
          menuBtnIcon.setAttribute("class", "ri-menu-3-line");
        }
      }
    });

    // Close menu when clicking on a link
    const navLinks = navLinksContainer.querySelectorAll('a');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        navLinksContainer.classList.remove("active");
        if (menuBtnIcon) {
          menuBtnIcon.setAttribute("class", "ri-menu-3-line");
        }
      });
    });
  }

  // Initialize categories slider
  initSlider();

  // Initialize ScrollReveal animations
  initScrollReveal();
});

function initSlider() {
  const imageList = document.querySelector(".slider-wrapper .image-list");
  const slideButtons = document.querySelectorAll(".slider-wrapper .slide-button");
  
  if (!imageList || !slideButtons.length) return;

  const maxScrollLeft = imageList.scrollWidth - imageList.clientWidth;

  // Slide images according to the slide button clicks
  slideButtons.forEach(button => {
    button.addEventListener("click", () => {
      const direction = button.id === "prev-slide" ? -1 : 1;
      const scrollAmount = imageList.clientWidth * direction;
      imageList.scrollBy({ left: scrollAmount, behavior: "smooth" });
    });
  });

  const handleSlideButtons = () => {
    if (!slideButtons[0] || !slideButtons[1]) return;
    
    slideButtons[0].style.display = imageList.scrollLeft <= 0 ? "none" : "block";
    slideButtons[1].style.display = imageList.scrollLeft >= maxScrollLeft ? "none" : "block";
  };

  // Update scrollbar thumb position
  const updateScrollbar = () => {
    const scrollbarThumb = document.querySelector('.scrollbar-thumb');
    if (scrollbarThumb) {
      const scrollPercentage = (imageList.scrollLeft / maxScrollLeft) * 100;
      scrollbarThumb.style.left = `${scrollPercentage}%`;
    }
  };

  imageList.addEventListener("scroll", () => {
    handleSlideButtons();
    updateScrollbar();
  });

  // Initial call
  handleSlideButtons();
  updateScrollbar();
}

function initScrollReveal() {
  if (typeof ScrollReveal === 'undefined') {
    console.log('ScrollReveal not loaded');
    return;
  }

  const sr = ScrollReveal({
    distance: '50px',
    duration: 1000,
    easing: 'ease',
    mobile: true,
    reset: false
  });

  // Header animations
  sr.reveal('.header__container h1', { 
    origin: 'top', 
    delay: 500,
    distance: '60px'
  });
  
  sr.reveal('.header__container h2', { 
    origin: 'top', 
    delay: 800,
    distance: '50px'
  });
  
  sr.reveal('.header__container p', { 
    origin: 'top', 
    delay: 1100,
    distance: '40px'
  });
  
  sr.reveal('.header__btn', { 
    origin: 'top', 
    delay: 1400,
    distance: '30px'
  });

  // Categories slider animation
  sr.reveal('.categories', {
    origin: 'bottom',
    delay: 200
  });

  // Featured products animations
  sr.reveal('.title', {
    origin: 'top',
    distance: '40px',
    delay: 200
  });

  sr.reveal('.col-4', {
    interval: 100,
    origin: 'bottom',
    distance: '30px',
    delay: 300
  });

  // Special offer section animations
  sr.reveal('.offer__image img', {
    origin: 'left',
    distance: '100px',
    delay: 300
  });

  sr.reveal('.offer__content h2', {
    origin: 'right',
    distance: '60px',
    delay: 400
  });

  sr.reveal('.offer__content h1', {
    origin: 'right',
    distance: '50px',
    delay: 500
  });

  sr.reveal('.offer__content p', {
    origin: 'right',
    distance: '40px',
    delay: 600
  });

  sr.reveal('.offer__btn', {
    origin: 'right',
    distance: '30px',
    delay: 700
  });

  // Testimonials animations
  sr.reveal('.testimonial .col-3', {
    interval: 150,
    origin: 'bottom',
    distance: '40px',
    delay: 200
  });

  // Brands animations
  sr.reveal('.brands .col-5', {
    interval: 80,
    origin: 'bottom',
    distance: '30px',
    delay: 100
  });
}

// Make initSlider available globally for window load event
window.initSlider = initSlider;

// assets/js/admin.js

document.addEventListener('DOMContentLoaded', function() {
  // Initialize admin dashboard
  initAdminDashboard();
  
  // Initialize charts if Chart.js is available
  if (typeof Chart !== 'undefined') {
    initAdminCharts();
  }
  
  // Initialize data tables
  initDataTables();
  
  // Initialize form validations
  initAdminForms();
});

function initAdminDashboard() {
  console.log('Admin dashboard initialized');
  
  // Stats cards animation
  const statCards = document.querySelectorAll('.stat-card');
  statCards.forEach((card, index) => {
    card.style.animationDelay = `${index * 0.1}s`;
    card.classList.add('fade-in');
  });
  
  // Toggle sidebar for mobile
  const sidebarToggle = document.querySelector('.sidebar-toggle');
  const sidebar = document.querySelector('.admin-sidebar');
  
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });
  }
  
  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('open')) {
      if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    }
  });
}

function initAdminCharts() {
  // Sales chart
  const salesCtx = document.getElementById('salesChart');
  if (salesCtx) {
    const salesChart = new Chart(salesCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'Sales',
          data: [12000, 19000, 15000, 25000, 22000, 30000, 28000, 35000, 32000, 40000, 38000, 45000],
          borderColor: '#242d25',
          backgroundColor: 'rgba(36, 45, 37, 0.1)',
          borderWidth: 2,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return '₱' + context.parsed.y.toLocaleString();
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '₱' + value.toLocaleString();
              }
            }
          }
        }
      }
    });
  }
  
  // Campus sales chart
  const campusCtx = document.getElementById('campusSalesChart');
  if (campusCtx) {
    const campusChart = new Chart(campusCtx, {
      type: 'bar',
      data: {
        labels: ['Main', 'East', 'West', 'North', 'South'],
        datasets: [{
          label: 'Sales per Campus',
          data: [45000, 28000, 32000, 19000, 24000],
          backgroundColor: [
            '#242d25',
            '#3a493b',
            '#ffd166',
            '#ed1d24',
            '#17a2b8'
          ]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return '₱' + context.parsed.y.toLocaleString();
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '₱' + value.toLocaleString();
              }
            }
          }
        }
      }
    });
  }
  
  // Product categories chart
  const categoriesCtx = document.getElementById('categoriesChart');
  if (categoriesCtx) {
    const categoriesChart = new Chart(categoriesCtx, {
      type: 'doughnut',
      data: {
        labels: ['Apparel', 'Accessories', 'Limited Edition', 'Best Sellers'],
        datasets: [{
          data: [45, 25, 20, 10],
          backgroundColor: [
            '#242d25',
            '#3a493b',
            '#ffd166',
            '#ed1d24'
          ]
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  }
}

function initDataTables() {
  // Initialize sortable tables if DataTables is available
  if (typeof $.fn.DataTable !== 'undefined') {
    $('.data-table').DataTable({
      pageLength: 10,
      responsive: true,
      language: {
        search: "Search:",
        lengthMenu: "Show _MENU_ entries",
        info: "Showing _START_ to _END_ of _TOTAL_ entries",
        paginate: {
          first: "First",
          last: "Last",
          next: "Next",
          previous: "Previous"
        }
      }
    });
  }
}

function initAdminForms() {
  // Image preview for product upload
  const imageInput = document.getElementById('product_image');
  const imagePreview = document.getElementById('image_preview');
  
  if (imageInput && imagePreview) {
    imageInput.addEventListener('change', function() {
      const file = this.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          imagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 200px; border-radius: 8px;">`;
        }
        reader.readAsDataURL(file);
      }
    });
  }
  
  // Multiple image upload
  const galleryInput = document.getElementById('product_gallery');
  const galleryPreview = document.getElementById('gallery_preview');
  
  if (galleryInput && galleryPreview) {
    galleryInput.addEventListener('change', function() {
      galleryPreview.innerHTML = '';
      Array.from(this.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
          const img = document.createElement('div');
          img.className = 'gallery-preview-item';
          img.innerHTML = `
            <img src="${e.target.result}" alt="Gallery preview">
            <button type="button" class="remove-image">&times;</button>
          `;
          galleryPreview.appendChild(img);
          
          // Remove image button
          img.querySelector('.remove-image').addEventListener('click', function() {
            img.remove();
          });
        }
        reader.readAsDataURL(file);
      });
    });
  }
  
  // Product form validation
  const productForm = document.getElementById('productForm');
  if (productForm) {
    productForm.addEventListener('submit', function(e) {
      const requiredFields = this.querySelectorAll('[required]');
      let isValid = true;
      
      requiredFields.forEach(field => {
        if (!field.value.trim()) {
          isValid = false;
          field.classList.add('is-invalid');
        }
      });
      
      if (!isValid) {
        e.preventDefault();
        showNotification('Please fill in all required fields', 'error');
      }
    });
  }
}

// Export data functionality
function exportData(type, format) {
  const exportButtons = document.querySelectorAll('.export-btn');
  exportButtons.forEach(btn => {
    btn.disabled = true;
    btn.innerHTML = '<i class="ri-loader-4-line spin"></i> Exporting...';
  });
  
  // Simulate export process
  setTimeout(() => {
    exportButtons.forEach(btn => {
      btn.disabled = false;
      btn.innerHTML = '<i class="ri-download-line"></i> Export';
    });
    
    showNotification(`${type} exported successfully as ${format}`, 'success');
    
    // In a real application, this would trigger a file download
    // window.location.href = `api/export.php?type=${type}&format=${format}`;
  }, 1500);
}

// Bulk actions
function performBulkAction(action) {
  const selectedItems = document.querySelectorAll('input[type="checkbox"]:checked');
  
  if (selectedItems.length === 0) {
    showNotification('Please select at least one item', 'error');
    return;
  }
  
  if (confirm(`Are you sure you want to ${action} ${selectedItems.length} item(s)?`)) {
    const itemIds = Array.from(selectedItems).map(input => input.value);
    
    // Show loading
    showNotification(`Processing ${action}...`, 'info');
    
    // In a real application, this would be an AJAX call
    setTimeout(() => {
      showNotification(`Successfully ${action}ed ${selectedItems.length} item(s)`, 'success');
      // Reload the page or update the table
      // location.reload();
    }, 1000);
  }
}

// Quick search for admin tables
function quickSearch(tableId, searchTerm) {
  const table = document.getElementById(tableId);
  if (!table) return;
  
  const rows = table.querySelectorAll('tbody tr');
  let visibleCount = 0;
  
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    if (text.includes(searchTerm.toLowerCase())) {
      row.style.display = '';
      visibleCount++;
    } else {
      row.style.display = 'none';
    }
  });
  
  // Update count
  const countElement = document.querySelector('.filtered-count');
  if (countElement) {
    countElement.textContent = `Showing ${visibleCount} of ${rows.length} items`;
  }
}