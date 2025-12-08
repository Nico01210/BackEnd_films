<template>
  <div class="comments-section">
    <h3>💬 Commentaires</h3>
    
    <!-- Formulaire de commentaire pour utilisateur connecté -->
    <div v-if="isLoggedIn" class="comment-form">
      <div v-if="!userComment" class="new-comment">
        <textarea 
          v-model="newCommentText" 
          placeholder="Écrivez votre commentaire..."
          rows="4"
          maxlength="500"
        ></textarea>
        <div class="form-actions">
          <span class="char-count">{{ newCommentText.length }}/500</span>
          <button @click="submitComment" :disabled="!newCommentText.trim() || submitting" class="btn-submit">
            {{ submitting ? 'Envoi...' : 'Publier' }}
          </button>
        </div>
      </div>
      
      <!-- Commentaire existant de l'utilisateur -->
      <div v-else class="user-comment">
        <div v-if="!editing" class="comment-display">
          <div class="comment-header">
            <strong>Votre commentaire</strong>
            <div class="comment-actions">
              <button @click="startEdit" class="btn-edit">✏️ Modifier</button>
              <button @click="deleteComment" class="btn-delete">🗑️ Supprimer</button>
            </div>
          </div>
          <p class="comment-text">{{ userComment.content }}</p>
          <span class="comment-date">{{ formatDate(userComment.createdAt) }}</span>
        </div>
        
        <div v-else class="edit-comment">
          <textarea 
            v-model="editCommentText" 
            rows="4"
            maxlength="500"
          ></textarea>
          <div class="form-actions">
            <span class="char-count">{{ editCommentText.length }}/500</span>
            <button @click="cancelEdit" class="btn-cancel">Annuler</button>
            <button @click="updateComment" :disabled="!editCommentText.trim() || submitting" class="btn-submit">
              {{ submitting ? 'Mise à jour...' : 'Mettre à jour' }}
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Message pour utilisateur non connecté -->
    <div v-else class="login-prompt">
      <p>Connectez-vous pour laisser un commentaire</p>
      <router-link to="/login" class="btn-login">Se connecter</router-link>
    </div>
    
    <!-- Liste des commentaires -->
    <div class="comments-list">
      <h4>Tous les commentaires ({{ comments.length }})</h4>
      <div v-if="comments.length === 0" class="no-comments">
        <p>Aucun commentaire pour le moment. Soyez le premier à commenter !</p>
      </div>
      <div v-else>
        <div v-for="comment in comments" :key="comment['@id'] || comment.id" class="comment-item">
          <div class="comment-header">
            <strong>{{ comment.user?.username || 'Utilisateur' }}</strong>
            <span class="comment-date">{{ formatDate(comment.createdAt) }}</span>
          </div>
          <p class="comment-text">{{ comment.content }}</p>
        </div>
      </div>
    </div>
    
    <p v-if="error" class="error">{{ error }}</p>
  </div>
</template>

<script>
import api from '../api/axios.js';

export default {
  props: {
    movieId: {
      type: [String, Number],
      required: true
    }
  },
  data() {
    return {
      comments: [],
      userComment: null,
      newCommentText: '',
      editCommentText: '',
      editing: false,
      submitting: false,
      error: null,
      isLoggedIn: false,
      currentUserId: null,
      currentUsername: null
    };
  },
  methods: {
    checkAuth() {
      const token = localStorage.getItem('token');
      this.isLoggedIn = !!token;
      // Récupérer les infos de l'utilisateur depuis localStorage
      this.currentUsername = localStorage.getItem('username');
      this.currentUserId = localStorage.getItem('userId');
      console.log('Auth check:', { isLoggedIn: this.isLoggedIn, username: this.currentUsername, userId: this.currentUserId });
    },
    
    async fetchComments() {
      try {
        console.log('Chargement des commentaires pour le film:', this.movieId);
        const res = await api.get(`/movies/${this.movieId}/reviews`);
        console.log('Commentaires reçus:', res.data);
        
        // API Platform utilise 'member' (pas hydra:member)
        const commentsData = res.data.member || res.data['hydra:member'] || [];
        this.comments = Array.isArray(commentsData) ? commentsData : [];
        
        console.log('Nombre de commentaires:', this.comments.length);
        console.log('Comments array:', this.comments);
        
        // Trouver le commentaire de l'utilisateur actuel
        // L'API retourne user: { id, username, ... }
        if (this.currentUsername && Array.isArray(this.comments)) {
          this.userComment = this.comments.find(c => c.user?.username === this.currentUsername);
          console.log('Commentaire de l\'utilisateur:', this.userComment);
        }
      } catch(err) {
        console.error('Erreur lors du chargement des commentaires:', err);
        console.error('Détails:', err.response?.data);
        this.comments = [];
      }
    },
    
    async submitComment() {
      if (!this.newCommentText.trim()) return;
      
      this.submitting = true;
      this.error = null;
      
      try {
        const payload = {
          content: this.newCommentText.trim(),
          movie: `/api/movies/${this.movieId}`
        };
        console.log('Envoi du commentaire:', payload);
        
        // Envoyer à l'endpoint spécifique du film
        const res = await api.post(`/movies/${this.movieId}/reviews`, payload);
        console.log('Réponse du serveur:', res.data);
        
        this.userComment = res.data;
        this.newCommentText = '';
        
        // Recharger tous les commentaires pour être sûr d'avoir la liste à jour
        await this.fetchComments();
      } catch(err) {
        console.error('Erreur complète:', err);
        console.error('Réponse du serveur:', err.response?.data);
        this.error = err.response?.data?.message || err.response?.data?.detail || err.response?.data?.['hydra:description'] || 'Erreur lors de l\'ajout du commentaire';
      } finally {
        this.submitting = false;
      }
    },
    
    startEdit() {
      this.editing = true;
      this.editCommentText = this.userComment.content;
    },
    
    cancelEdit() {
      this.editing = false;
      this.editCommentText = '';
    },
    
    async updateComment() {
      if (!this.editCommentText.trim()) return;
      
      this.submitting = true;
      this.error = null;
      
      try {
        const reviewId = this.userComment.id;
        const res = await api.patch(`/reviews/${reviewId}`, {
          content: this.editCommentText.trim()
        }, {
          headers: {
            'Content-Type': 'application/merge-patch+json'
          }
        });
        
        this.userComment = res.data;
        // Mettre à jour dans la liste
        const index = this.comments.findIndex(c => c.id === this.userComment.id);
        if (index !== -1) {
          this.comments[index] = res.data;
        }
        this.editing = false;
        this.editCommentText = '';
      } catch(err) {
        this.error = err.response?.data?.message || err.response?.data?.detail || 'Erreur lors de la mise à jour du commentaire';
        console.error(err);
      } finally {
        this.submitting = false;
      }
    },
    
    async deleteComment() {
      if (!confirm('Êtes-vous sûr de vouloir supprimer votre commentaire ?')) {
        return;
      }
      
      this.submitting = true;
      this.error = null;
      
      try {
        const reviewId = this.userComment.id;
        await api.delete(`/reviews/${reviewId}`);
        
        // Retirer de la liste
        this.comments = this.comments.filter(c => c.id !== this.userComment.id);
        this.userComment = null;
      } catch(err) {
        this.error = err.response?.data?.message || err.response?.data?.detail || 'Erreur lors de la suppression du commentaire';
        console.error(err);
      } finally {
        this.submitting = false;
      }
    },
    
    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    }
  },
  mounted() {
    this.checkAuth();
    this.fetchComments();
  }
};
</script>

<style scoped>
.comments-section {
  margin-top: 40px;
  padding: 30px;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.comments-section h3 {
  margin: 0 0 20px 0;
  color: #333;
  font-size: 24px;
}

.comment-form {
  margin-bottom: 30px;
  padding: 20px;
  background: #f8f9fa;
  border-radius: 8px;
}

.new-comment textarea,
.edit-comment textarea {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  font-family: inherit;
  resize: vertical;
  box-sizing: border-box;
}

.new-comment textarea:focus,
.edit-comment textarea:focus {
  outline: none;
  border-color: #667eea;
}

.form-actions {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10px;
}

.char-count {
  font-size: 12px;
  color: #666;
}

.btn-submit,
.btn-cancel,
.btn-edit,
.btn-delete {
  padding: 8px 16px;
  border: none;
  border-radius: 4px;
  font-size: 14px;
  cursor: pointer;
  transition: background-color 0.3s;
}

.btn-submit {
  background-color: #667eea;
  color: white;
  margin-left: 10px;
}

.btn-submit:hover:not(:disabled) {
  background-color: #5568d3;
}

.btn-submit:disabled {
  background-color: #ccc;
  cursor: not-allowed;
}

.btn-cancel {
  background-color: #6c757d;
  color: white;
}

.btn-cancel:hover {
  background-color: #5a6268;
}

.btn-edit,
.btn-delete {
  padding: 6px 12px;
  font-size: 12px;
  margin-left: 8px;
}

.btn-edit {
  background-color: #ffc107;
  color: #333;
}

.btn-edit:hover {
  background-color: #e0a800;
}

.btn-delete {
  background-color: #dc3545;
  color: white;
}

.btn-delete:hover {
  background-color: #c82333;
}

.user-comment {
  padding: 15px;
  background: #e8f4f8;
  border-left: 4px solid #667eea;
  border-radius: 4px;
}

.comment-display {
  position: relative;
}

.comment-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.comment-actions {
  display: flex;
  gap: 5px;
}

.comment-text {
  margin: 10px 0;
  line-height: 1.6;
  color: #333;
  white-space: pre-wrap;
}

.comment-date {
  font-size: 12px;
  color: #666;
}

.login-prompt {
  text-align: center;
  padding: 30px;
  background: #f8f9fa;
  border-radius: 8px;
  margin-bottom: 30px;
}

.login-prompt p {
  margin: 0 0 15px 0;
  color: #666;
}

.btn-login {
  display: inline-block;
  padding: 10px 24px;
  background-color: #667eea;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  transition: background-color 0.3s;
}

.btn-login:hover {
  background-color: #5568d3;
}

.comments-list {
  margin-top: 30px;
}

.comments-list h4 {
  margin: 0 0 20px 0;
  color: #555;
  font-size: 18px;
}

.no-comments {
  text-align: center;
  padding: 30px;
  color: #999;
}

.comment-item {
  padding: 15px;
  margin-bottom: 15px;
  background: #f8f9fa;
  border-radius: 4px;
  border-left: 3px solid #ddd;
}

.comment-item:hover {
  background: #e9ecef;
}

.error {
  color: #dc3545;
  margin-top: 10px;
  font-size: 14px;
}

@media (max-width: 768px) {
  .comments-section {
    padding: 20px;
  }
  
  .comment-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }
  
  .form-actions {
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
  }
  
  .btn-submit {
    margin-left: 0;
    width: 100%;
  }
}
</style>
