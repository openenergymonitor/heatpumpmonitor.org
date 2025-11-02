<?php
// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');
?>

<link rel="stylesheet" type="text/css" href="<?php echo $path; ?>Modules/system/system_view.css">
<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/axios/1.4.0/axios.min.js"></script>

<div id="app">
    <div style="background-color:#f0f0f0; padding-top:20px; padding-bottom:10px">
        <div class="container-fluid">
            <h3>System Photos</h3>
            <p>Manage all uploaded system photos. Total photos: {{ pagination.total_photos }}</p>
        </div>
    </div>
    
    <div class="container-fluid">
        <br>
        
        <!-- Loading indicator -->
        <div v-if="loading" class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        
        <!-- Photos table -->
        <div v-if="!loading" class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col" style="width:100px">Photo</th>
                        <th scope="col" style="width:150px">Upload Date</th>
                        <th scope="col" style="width:100px">System ID</th>
                        <th scope="col">System Info</th>
                        <th scope="col" style="width:150px">Filename</th>
                        <th scope="col" style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="photo in photos" :key="photo.id">
                        <td>
                            <div class="photo-thumbnail-item" style="width: 80px; height: 60px; cursor: pointer;" @click="openLightbox(photo)">
                                <img 
                                    :src="selectThumbnail(photo, '80x60')" 
                                    :alt="photo.original_filename"
                                    class="gallery-thumbnail"
                                >
                                <div class="thumbnail-overlay">
                                    <i class="fas fa-expand-alt"></i>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:14px">{{ formatDate(photo.date_uploaded) }}</td>
                        <td>
                            <a :href="path + 'system/view?id=' + photo.system_id" target="_blank">
                                {{ photo.system_id }}
                            </a>
                        </td>
                        <td>
                            <div style="max-width: 300px;">
                                <div v-if="photo.system_info" style="font-weight: bold;">{{ photo.system_info }}</div>
                                <div v-if="photo.system_location" style="color: #666; font-size: 0.9em;">{{ photo.system_location }}</div>
                            </div>
                        </td>
                        <td style="font-size:14px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ photo.original_filename }}
                        </td>
                        <td>
                            <button 
                                class="btn btn-danger btn-sm" 
                                @click="confirmDelete(photo)"
                                title="Delete photo"
                            >
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- No photos message -->
        <div v-if="!loading && photos.length === 0" class="alert alert-info">
            No photos have been uploaded yet.
        </div>
        
        <!-- Pagination -->
        <nav v-if="!loading && pagination.total_pages > 1" aria-label="Photo pagination">
            <ul class="pagination justify-content-center">
                <li class="page-item" :class="{ disabled: !pagination.has_prev }">
                    <a class="page-link" href="#" @click.prevent="changePage(pagination.current_page - 1)" :disabled="!pagination.has_prev">
                        Previous
                    </a>
                </li>
                
                <li 
                    v-for="page in visiblePages" 
                    :key="page"
                    class="page-item" 
                    :class="{ active: page === pagination.current_page }"
                >
                    <a class="page-link" href="#" @click.prevent="changePage(page)">{{ page }}</a>
                </li>
                
                <li class="page-item" :class="{ disabled: !pagination.has_next }">
                    <a class="page-link" href="#" @click.prevent="changePage(pagination.current_page + 1)" :disabled="!pagination.has_next">
                        Next
                    </a>
                </li>
            </ul>
        </nav>
        
        <div v-if="!loading && pagination.total_pages > 1" class="text-center text-muted small">
            Page {{ pagination.current_page }} of {{ pagination.total_pages }} 
            ({{ pagination.total_photos }} total photos)
        </div>
    </div>
    
    <!-- Photo Lightbox -->
    <div v-if="lightboxOpen" class="photo-lightbox" @click="closeLightbox">
        <div class="lightbox-content" @click.stop>
            <button class="lightbox-close" @click="closeLightbox">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="lightbox-image-container">
                <img 
                    :src="path + currentPhoto.url" 
                    :alt="currentPhoto.original_filename"
                    class="lightbox-image"
                >
                <div class="lightbox-caption">
                    System {{ currentPhoto.system_id }} - {{ currentPhoto.original_filename }}
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-backdrop">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this photo?</p>
                    <div class="text-center">
                        <div class="photo-thumbnail-item" style="width: 200px; height: 150px; margin: 0 auto;">
                            <img 
                                :src="selectThumbnail(photoToDelete, '300')" 
                                :alt="photoToDelete.original_filename"
                                class="gallery-thumbnail"
                            >
                        </div>
                    </div>
                    <p class="mt-2">
                        <strong>File:</strong> {{ photoToDelete.original_filename }}<br>
                        <strong>System:</strong> {{ photoToDelete.system_id }}
                        <span v-if="photoToDelete.system_info"> - {{ photoToDelete.system_info }}</span>
                    </p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This action cannot be undone.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" @click="cancelDelete">Cancel</button>
                    <button type="button" class="btn btn-danger" @click="deletePhoto" :disabled="deleting">
                        <span v-if="deleting">Deleting...</span>
                        <span v-else>Delete Photo</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Admin-specific modal styles */
.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-dialog {
    max-width: 500px;
    width: 90%;
}

.modal-content {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.modal-header {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 15px 20px;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 500;
}
</style>

<script>
var path = "<?php echo $path; ?>";

var app = new Vue({
    el: '#app',
    data: {
        photos: [],
        pagination: {
            current_page: 1,
            total_pages: 1,
            total_photos: 0,
            limit: 20,
            has_next: false,
            has_prev: false
        },
        loading: true,
        path: path,
        lightboxOpen: false,
        currentPhoto: {},
        showDeleteModal: false,
        photoToDelete: {},
        deleting: false
    },
    computed: {
        visiblePages: function() {
            const current = this.pagination.current_page;
            const total = this.pagination.total_pages;
            const pages = [];
            
            // Show up to 7 pages around current page
            const start = Math.max(1, current - 3);
            const end = Math.min(total, current + 3);
            
            for (let i = start; i <= end; i++) {
                pages.push(i);
            }
            
            return pages;
        }
    },
    methods: {
        loadPhotos: function(page = 1) {
            this.loading = true;
            
            axios.get(path + 'system/photos/admin.json?page=' + page + '&limit=20')
                .then(response => {
                    if (response.data.success) {
                        this.photos = response.data.photos;
                        this.pagination = response.data.pagination;
                    } else {
                        console.error('Failed to load photos:', response.data.message);
                    }
                    this.loading = false;
                })
                .catch(error => {
                    console.error('Error loading photos:', error);
                    this.loading = false;
                });
        },
        
        changePage: function(page) {
            if (page >= 1 && page <= this.pagination.total_pages) {
                this.loadPhotos(page);
                // Scroll to top
                window.scrollTo(0, 0);
            }
        },
        
        openLightbox: function(photo) {
            this.currentPhoto = photo;
            this.lightboxOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        closeLightbox: function() {
            this.lightboxOpen = false;
            this.currentPhoto = {};
            document.body.style.overflow = '';
        },
        
        confirmDelete: function(photo) {
            this.photoToDelete = photo;
            this.showDeleteModal = true;
        },
        
        cancelDelete: function() {
            this.showDeleteModal = false;
            this.photoToDelete = {};
            this.deleting = false;
        },
        
        deletePhoto: function() {
            this.deleting = true;
            
            axios.post(path + 'system/photos/delete.json?photo_id=' + this.photoToDelete.id)
                .then(response => {
                    if (response.data.success) {
                        // Remove photo from the list
                        const index = this.photos.findIndex(p => p.id === this.photoToDelete.id);
                        if (index !== -1) {
                            this.photos.splice(index, 1);
                        }
                        
                        // Update pagination
                        this.pagination.total_photos--;
                        
                        // If current page is empty and not the first page, go to previous page
                        if (this.photos.length === 0 && this.pagination.current_page > 1) {
                            this.changePage(this.pagination.current_page - 1);
                        }
                        
                        this.cancelDelete();
                    } else {
                        alert('Failed to delete photo: ' + response.data.message);
                        this.deleting = false;
                    }
                })
                .catch(error => {
                    alert('Error deleting photo: ' + (error.response?.data?.message || 'Unknown error'));
                    this.deleting = false;
                });
        },
        
        formatDate: function(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        // Select the best thumbnail size for display
        selectThumbnail: function(photo, desired_width = 80, desired_height = 60) {
            // If photo doesn't have thumbnails, use original
            if (!photo.thumbnails || !Array.isArray(photo.thumbnails) || photo.thumbnails.length === 0) {
                return this.path + photo.url;
            }

            // Try to find exact match by dimensions
            const exact_match = photo.thumbnails.find(thumb => 
                thumb.width === desired_width && thumb.height === desired_height
            );
            if (exact_match) {
                return this.path + exact_match.url;
            }

            // For backward compatibility, support string-based size requests
            if (typeof desired_width === 'string') {
                if (desired_width === '80x60') {
                    desired_width = 80;
                    desired_height = 60;
                } else {
                    // For square sizes like "150", "300"
                    const size = parseInt(desired_width);
                    desired_width = size;
                    desired_height = size;
                }
                
                // Try exact match again with parsed dimensions
                const parsed_match = photo.thumbnails.find(thumb => 
                    thumb.width === desired_width && thumb.height === desired_height
                );
                if (parsed_match) {
                    return this.path + parsed_match.url;
                }
            }

            // Find the best fit thumbnail (smallest that's >= desired size)
            const suitable_thumbs = photo.thumbnails.filter(thumb => 
                thumb.width >= desired_width && thumb.height >= desired_height
            );
            
            if (suitable_thumbs.length > 0) {
                // Sort by area (width * height) and pick the smallest
                suitable_thumbs.sort((a, b) => (a.width * a.height) - (b.width * b.height));
                return this.path + suitable_thumbs[0].url;
            }

            // If no suitable thumbnail found, use the largest available
            if (photo.thumbnails.length > 0) {
                const largest = photo.thumbnails.reduce((max, thumb) => 
                    (thumb.width * thumb.height) > (max.width * max.height) ? thumb : max
                );
                return this.path + largest.url;
            }
            
            // Fallback to original image
            return this.path + photo.url;
        }
    },
    
    mounted: function() {
        this.loadPhotos();
        
        // Handle escape key for lightbox
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (this.lightboxOpen) {
                    this.closeLightbox();
                } else if (this.showDeleteModal) {
                    this.cancelDelete();
                }
            }
        });
    }
});
</script>