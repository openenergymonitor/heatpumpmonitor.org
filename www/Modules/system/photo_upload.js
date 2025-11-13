/**
 * Photo Upload Mixin
 * Shared functionality for photo upload across components
 */
var PhotoUploadMixin = {
    data: function() {
        return {
            system_photos: [],
            show_photo_upload: true,
            show_other_photo_upload: false,
            isDragActive: false,
            isDragActiveType: null,
            max_photos: 4,
            max_file_size: 5 * 1024 * 1024, // 5MB
            allowed_types: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
            show_photo_error: false,
            photo_message: ''
        };
    },

    methods: {
        triggerFileSelect: function() {
            this.$refs.fileInput.click();
        },

        handleFileSelect: function(event) {
            const files = Array.from(event.target.files);
            this.processFiles(files);
            // Clear the input so the same file can be selected again
            event.target.value = '';
        },

        handleDragOver: function(event) {
            event.preventDefault();
            this.isDragActive = true;
        },

        handleDragLeave: function(event) {
            event.preventDefault();
            this.isDragActive = false;
        },

        handleDrop: function(event) {
            event.preventDefault();
            this.isDragActive = false;
            const files = Array.from(event.dataTransfer.files);
            this.processFiles(files);
        },

        processFiles: function(files) {
            // Filter valid image files
            const validFiles = files.filter(file => {
                const validation = PhotoUtils.validateFile(file, {
                    maxSize: this.max_file_size,
                    allowedTypes: this.allowed_types
                });
                
                if (!validation.valid) {
                    this.showFileError(validation.error);
                    return false;
                }
                return true;
            });

            // Check if we would exceed max photos
            const totalPhotos = this.system_photos.length + validFiles.length;
            if (totalPhotos > this.max_photos) {
                const allowedFiles = this.max_photos - this.system_photos.length;
                this.showFileError(`You can only upload ${allowedFiles} more photo(s). Maximum is ${this.max_photos} photos.`);
                return;
            }

            this.show_photo_upload = validFiles.length === 0;

            // Process each valid file
            validFiles.forEach(file => {
                this.addPhoto(file);
            });
        },

        addPhoto: function(file) {
            // Create file reader for preview
            const reader = new FileReader();
            reader.onload = (e) => {
                const photo = {
                    id: Date.now() + Math.random(),
                    file: file,
                    name: file.name,
                    size: file.size,
                    preview: e.target.result,
                    uploading: false,
                    uploaded: false,
                    progress: 0,
                    error: null
                };
                
                this.system_photos.push(photo);
                // Auto-upload the photo
                this.uploadPhoto(photo);
            };
            reader.readAsDataURL(file);
        },

        removePhoto: function(index) {
            const photo = this.system_photos[index];
            
            // If photo has an ID, it's been uploaded to server, so delete it
            if (photo.id && photo.uploaded) {
                if (confirm('Are you sure you want to delete this photo?')) {
                    axios.post(this.path + 'system/delete-photo?photo_id=' + photo.id)
                        .then(response => {
                            if (response.data.success) {
                                this.system_photos.splice(index, 1);
                                this.updatePhotoUploadVisibility();
                            } else {
                                alert('Failed to delete photo: ' + response.data.message);
                            }
                        })
                        .catch(error => {
                            alert('Failed to delete photo: ' + (error.response?.data?.message || 'Unknown error'));
                        });
                }
            } else {
                // Photo is still being uploaded or failed, just remove from array
                this.system_photos.splice(index, 1);
                this.updatePhotoUploadVisibility();
            }
        },

        uploadPhoto: function(photo) {
            photo.uploading = true;
            photo.progress = 0;

            // Create FormData for upload
            const formData = new FormData();
            formData.append('photo', photo.file);
            formData.append('system_id', this.system.id);

            // Upload to server
            axios.post(this.path + 'system/upload-photo', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                },
                onUploadProgress: (progressEvent) => {
                    photo.progress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                }
            })
            .then(response => {
                photo.uploading = false;
                if (response.data.success) {
                    photo.uploaded = true;
                    photo.url = response.data.url;
                    photo.server_url = response.data.url;
                    photo.id = response.data.image_id;
                    photo.thumbnails = response.data.thumbnails || [];
                    
                    // Debug thumbnail generation status
                    if (response.data.thumbnail_generation) {
                        const tg = response.data.thumbnail_generation;
                        console.log(`Thumbnail generation: ${tg.success ? 'SUCCESS' : 'FAILED'}, Count: ${tg.count}`);
                        if (tg.errors) {
                            console.error('Thumbnail errors:', tg.errors);
                        }
                    }
                    
                    this.updatePhotoUploadVisibility();
                } else {
                    photo.error = response.data.message || 'Upload failed. Please try again.';
                }
            })
            .catch(error => {
                photo.uploading = false;
                photo.error = error.response?.data?.message || 'Upload failed. Please try again.';
            });
        },

        updatePhotoUploadVisibility: function() {
            this.show_photo_upload = this.system_photos.length === 0;
        },

        showFileError: function(message) {
            this.show_photo_error = true;
            this.photo_message = message;
            // Auto-hide error after 5 seconds
            setTimeout(() => {
                this.show_photo_error = false;
            }, 5000);
        },

        loadExistingPhotos: function(systemId) {
            if (!systemId) return;
            
            axios.get(this.path + 'system/photos?id=' + systemId)
                .then(response => {
                    if (response.data.success) {
                        this.system_photos = response.data.photos.map(photo => {
                            return {
                                id: photo.id,
                                photo_type: photo.photo_type || 'other',
                                name: photo.original_filename,
                                preview: this.path + photo.url,
                                server_url: this.path + photo.url,
                                url: photo.url,
                                uploading: false,
                                uploaded: true,
                                progress: 100,
                                error: null,
                                width: photo.width,
                                height: photo.height,
                                file_size: photo.file_size,
                                date_uploaded: photo.date_uploaded,
                                thumbnails: photo.thumbnails || []
                            };
                        });
                    }
                    this.updatePhotoUploadVisibility();
                })
                .catch(error => {
                    console.log('Error loading photos:', error);
                    this.updatePhotoUploadVisibility();
                });
        }
    },

    computed: {
        hasPhotos: function() {
            return this.system_photos && this.system_photos.length > 0;
        }
    }
};