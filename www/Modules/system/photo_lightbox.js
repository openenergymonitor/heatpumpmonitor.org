/**
 * Photo Lightbox Mixin
 * Shared functionality for photo gallery lightbox across multiple components
 */
var PhotoLightboxMixin = {
    data: function() {
        return {
            lightboxOpen: false,
            currentPhotoIndex: 0,
            system_photos: [],
            loadingPhotos: false
        };
    },
    
    methods: {
        openLightbox: function(index) {
            if (!this.system_photos || this.system_photos.length === 0) return;
            this.currentPhotoIndex = index;
            this.lightboxOpen = true;
            this.addKeyboardListeners();
            // Prevent body scrolling when lightbox is open
            document.body.style.overflow = 'hidden';
        },
        
        closeLightbox: function() {
            this.lightboxOpen = false;
            this.currentPhotoIndex = 0;
            this.removeKeyboardListeners();
            // Restore body scrolling
            document.body.style.overflow = '';
        },
        
        nextPhoto: function() {
            if (!this.system_photos || this.system_photos.length <= 1) return;
            this.currentPhotoIndex = (this.currentPhotoIndex + 1) % this.system_photos.length;
        },
        
        previousPhoto: function() {
            if (!this.system_photos || this.system_photos.length <= 1) return;
            this.currentPhotoIndex = (this.currentPhotoIndex - 1 + this.system_photos.length) % this.system_photos.length;
        },
        
        addKeyboardListeners: function() {
            document.addEventListener('keydown', this.handleKeydown);
        },
        
        removeKeyboardListeners: function() {
            document.removeEventListener('keydown', this.handleKeydown);
        },
        
        handleKeydown: function(event) {
            if (!this.lightboxOpen) return;
            
            switch(event.key) {
                case 'Escape':
                    event.preventDefault();
                    this.closeLightbox();
                    break;
                case 'ArrowLeft':
                    event.preventDefault();
                    this.previousPhoto();
                    break;
                case 'ArrowRight':
                    event.preventDefault();
                    this.nextPhoto();
                    break;
            }
        },
        
        formatPhotoType: function(photo_type) {
            if (!photo_type) return '';
            switch(photo_type) {
                case 'outdoor_unit':
                    return 'Outdoor Unit';
                case 'plant_room':
                    return 'Plant Room';
                case 'other':
                    return 'Other';
                default:
                    return photo_type.charAt(0).toUpperCase() + photo_type.slice(1);
            }
        },
        
        // Load photos for a specific system (used by system list)
        openSystemPhotos: function(systemId) {
            this.loadingPhotos = true;
            this.system_photos = [];
            
            axios.get(path + 'system/photos?id=' + systemId)
                .then(response => {
                    if (response.data.success && response.data.photos.length > 0) {
                        this.system_photos = response.data.photos.map(photo => {
                            return {
                                id: photo.id,
                                name: photo.original_filename,
                                photo_type: photo.photo_type,
                                url: photo.url,
                                width: photo.width,
                                height: photo.height,
                                thumbnails: photo.thumbnails || []
                            };
                        });
                        this.currentPhotoIndex = 0;
                        this.lightboxOpen = true;
                        this.addKeyboardListeners();
                    }
                    this.loadingPhotos = false;
                })
                .catch(error => {
                    console.log('Error loading photos:', error);
                    this.loadingPhotos = false;
                });
        }
    },
    
    // Cleanup when component is destroyed
    beforeDestroy: function() {
        this.removeKeyboardListeners();
        // Restore body scrolling
        document.body.style.overflow = '';
    }
};