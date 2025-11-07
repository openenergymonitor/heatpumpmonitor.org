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
            loadingPhotos: false,
            // Mobile touch support
            touchStartX: 0,
            touchStartY: 0,
            touchEndX: 0,
            touchEndY: 0,
            touchStartTime: 0,
            overlayVisible: true
        };
    },
    
    methods: {
        openLightbox: function(index) {
            if (!this.system_photos || this.system_photos.length === 0) return;
            this.currentPhotoIndex = index;
            this.lightboxOpen = true;
            this.overlayVisible = true;
            this.addKeyboardListeners();
            // Prevent body scrolling when lightbox is open
            document.body.style.overflow = 'hidden';
        },
        
        closeLightbox: function() {
            this.lightboxOpen = false;
            this.currentPhotoIndex = 0;
            this.overlayVisible = true;
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
        
        // Click handlers
        handleBackgroundClick: function() {
            // Clicking outside the image closes lightbox
            this.closeLightbox();
        },
        
        handleImageClick: function(event) {
            // Clicking on image toggles overlay
            event.stopPropagation();
            this.toggleOverlay();
        },
        
        // Mobile touch support methods
        handleTouchStart: function(event) {
            if (!this.lightboxOpen) return;
            
            if (event.touches.length === 1) {
                // Single touch - prepare for swipe
                this.touchStartX = event.touches[0].clientX;
                this.touchStartY = event.touches[0].clientY;
                this.touchStartTime = Date.now();
            }
        },

        handleTouchEnd: function(event) {
            if (!this.lightboxOpen) return;
            
            if (event.changedTouches.length === 1 && event.touches.length === 0) {
                // Single touch ended
                this.touchEndX = event.changedTouches[0].clientX;
                this.touchEndY = event.changedTouches[0].clientY;
                
                const deltaX = this.touchEndX - this.touchStartX;
                const deltaY = this.touchEndY - this.touchStartY;
                const swipeThreshold = 50;
                const tapThreshold = 10;
                const touchDuration = Date.now() - (this.touchStartTime || 0);
                
                // Check if it's a tap (small movement and quick)
                if (Math.abs(deltaX) < tapThreshold && Math.abs(deltaY) < tapThreshold && touchDuration < 300) {
                    // Don't toggle overlay here - it's handled by handleImageClick
                    return;
                }
                
                // Handle swipes if horizontal movement is significant
                if (Math.abs(deltaX) > swipeThreshold && Math.abs(deltaX) > Math.abs(deltaY)) {
                    event.preventDefault();
                    if (deltaX > 0) {
                        // Swipe right - previous photo
                        this.previousPhotoWithAnimation();
                    } else {
                        // Swipe left - next photo
                        this.nextPhotoWithAnimation();
                    }
                }
            }
        },

        // Navigation with slide animation
        nextPhotoWithAnimation: function() {
            if (!this.system_photos || this.system_photos.length <= 1) return;
            this.slideToPhoto((this.currentPhotoIndex + 1) % this.system_photos.length, 'left');
        },
        
        previousPhotoWithAnimation: function() {
            if (!this.system_photos || this.system_photos.length <= 1) return;
            this.slideToPhoto((this.currentPhotoIndex - 1 + this.system_photos.length) % this.system_photos.length, 'right');
        },
        
        slideToPhoto: function(newIndex, direction) {
            const container = document.querySelector('.lightbox-image-container');
            if (!container) return;
            
            // Add slide animation
            container.style.transition = 'transform 0.3s ease-out';
            container.style.transform = direction === 'left' ? 'translateX(-100%)' : 'translateX(100%)';
            
            // Change photo after brief delay
            setTimeout(() => {
                this.currentPhotoIndex = newIndex;
                container.style.transition = 'none';
                container.style.transform = direction === 'left' ? 'translateX(100%)' : 'translateX(-100%)';
                
                // Slide in new photo
                setTimeout(() => {
                    container.style.transition = 'transform 0.3s ease-out';
                    container.style.transform = 'translateX(0)';
                }, 50);
            }, 150);
        },
        
        toggleOverlay: function() {
            this.overlayVisible = !this.overlayVisible;
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