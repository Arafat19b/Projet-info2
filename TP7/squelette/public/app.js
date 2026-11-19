'use strict';

/**
 * Frontend TP7 — Appels fetch JSON, gestion lisible des erreurs (400, 401, 403, 409, 500)
 * et rendu sécurisé contre les failles XSS (usage exclusif de textContent).
 */

const etat = {
  utilisateur: null,
};

// Utilitaires d'affichage sécurisé
function afficherAlerte(type, message) {
  const alerte = document.getElementById('alerte-globale');
  alerte.className = 'alerte ' + (type === 'succes' ? 'alerte-succes' : 'alerte-erreur');
  alerte.textContent = message;
  alerte.hidden = false;
}

function masquerAlerte() {
  const alerte = document.getElementById('alerte-globale');
  alerte.hidden = true;
  alerte.textContent = '';
}

function afficherErreurChamp(idChamp, message) {
  const zone = document.getElementById('erreur-' + idChamp);
  if (zone) {
    zone.textContent = message;
  }
}

function effacerErreursChamps() {
  document.querySelectorAll('.erreur-champ').forEach((el) => {
    el.textContent = '';
  });
}

// Client HTTP centralisé gérant 401 (redirection connexion) et erreurs JSON
async function apiFetch(url, options = {}) {
  const reponse = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  });

  if (reponse.status === 401 && url !== '/api/connexion' && url !== '/api/moi') {
    etat.utilisateur = null;
    basculerVueConnexion();
    afficherAlerte('erreur', 'Votre session a expiré. Veuillez vous reconnecter.');
    return null;
  }

  let donnees = null;
  try {
    donnees = await reponse.json();
  } catch {
    donnees = { message: 'Réponse invalide du serveur.' };
  }

  return { ok: reponse.ok, status: reponse.status, data: donnees };
}

// Gestion des écrans selon le rôle
function basculerVueConnexion() {
  document.getElementById('vue-connexion').hidden = false;
  document.getElementById('vue-etudiant').hidden = true;
  document.getElementById('vue-responsable').hidden = true;
  document.getElementById('barre-utilisateur').hidden = true;
}

async function afficherEspaceUtilisateur(utilisateur) {
  etat.utilisateur = utilisateur;
  masquerAlerte();
  effacerErreursChamps();

  document.getElementById('vue-connexion').hidden = true;
  document.getElementById('barre-utilisateur').hidden = false;

  const info = document.getElementById('info-utilisateur');
  info.textContent = `${utilisateur.prenom} ${utilisateur.nom} (${utilisateur.role})`;

  if (utilisateur.role === 'ETUDIANT') {
    document.getElementById('vue-etudiant').hidden = false;
    document.getElementById('vue-responsable').hidden = true;
    await chargerEntreprises();
    await chargerDemandesEtudiant();
  } else {
    document.getElementById('vue-etudiant').hidden = true;
    document.getElementById('vue-responsable').hidden = false;
    await chargerDemandesResponsable();
  }
}

// Chargement de la liste des entreprises (rendu via textContent)
async function chargerEntreprises() {
  const select = document.getElementById('entrepriseId');
  const res = await apiFetch('/api/entreprises', { method: 'GET' });
  if (!res || !res.ok) return;

  select.innerHTML = '';
  const optDefaut = document.createElement('option');
  optDefaut.value = '';
  optDefaut.textContent = '-- Choisir une entreprise --';
  select.appendChild(optDefaut);

  res.data.forEach((ent) => {
    const opt = document.createElement('option');
    opt.value = String(ent.id);
    opt.textContent = `${ent.nom} (${ent.ville})`;
    select.appendChild(opt);
  });
}

// Rendu sécurisé de la liste des demandes pour l'étudiant
async function chargerDemandesEtudiant() {
  const conteneur = document.getElementById('liste-demandes-etudiant');
  const res = await apiFetch('/api/demandes', { method: 'GET' });
  if (!res) return;

  if (!res.ok) {
    afficherAlerte('erreur', res.data.message || 'Impossible de charger vos demandes.');
    return;
  }

  conteneur.innerHTML = '';
  if (res.data.length === 0) {
    const vide = document.createElement('p');
    vide.textContent = 'Aucune demande enregistrée pour le moment.';
    conteneur.appendChild(vide);
    return;
  }

  res.data.forEach((demande) => {
    const item = document.createElement('article');
    item.className = 'dossier-item';

    const infos = document.createElement('div');
    infos.className = 'dossier-infos';

    const titre = document.createElement('p');
    const fort = document.createElement('strong');
    fort.textContent = `Demande #${demande.id} — ${demande.entreprise_nom}`;
    titre.appendChild(fort);
    titre.appendChild(document.createTextNode(' '));

    const badge = document.createElement('span');
    badge.className = `statut-badge statut-${demande.statut}`;
    badge.textContent = demande.statut;
    titre.appendChild(badge);
    infos.appendChild(titre);

    if (demande.motif_refus) {
      const motif = document.createElement('p');
      motif.textContent = `Motif du refus : ${demande.motif_refus}`;
      infos.appendChild(motif);
    }

    item.appendChild(infos);

    // Actions possibles selon le statut (BROUILLON -> SOUMISE, REFUSEE -> BROUILLON)
    const actions = document.createElement('div');
    actions.className = 'actions-ligne';

    if (demande.statut === 'BROUILLON') {
      const btnSoumettre = document.createElement('button');
      btnSoumettre.type = 'button';
      btnSoumettre.className = 'btn btn-primaire';
      btnSoumettre.textContent = 'Soumettre';
      btnSoumettre.addEventListener('click', () => modifierStatutDemande(demande.id, 'SOUMISE', null));
      actions.appendChild(btnSoumettre);
    } else if (demande.statut === 'REFUSEE') {
      const btnCorriger = document.createElement('button');
      btnCorriger.type = 'button';
      btnCorriger.className = 'btn btn-secondaire';
      btnCorriger.textContent = 'Repasser en brouillon';
      btnCorriger.addEventListener('click', () => modifierStatutDemande(demande.id, 'BROUILLON', null));
      actions.appendChild(btnCorriger);
    }

    item.appendChild(actions);
    conteneur.appendChild(item);
  });
}

// Rendu sécurisé de la liste des demandes pour le responsable / secrétariat
async function chargerDemandesResponsable() {
  const conteneur = document.getElementById('liste-demandes-responsable');
  const res = await apiFetch('/api/demandes', { method: 'GET' });
  if (!res) return;

  if (!res.ok) {
    afficherAlerte('erreur', res.data.message || 'Erreur lors du chargement des dossiers.');
    return;
  }

  conteneur.innerHTML = '';
  if (res.data.length === 0) {
    const vide = document.createElement('p');
    vide.textContent = 'Aucun dossier de stage trouvé.';
    conteneur.appendChild(vide);
    return;
  }

  res.data.forEach((demande) => {
    const item = document.createElement('article');
    item.className = 'dossier-item';

    const infos = document.createElement('div');
    infos.className = 'dossier-infos';

    const titre = document.createElement('p');
    const fort = document.createElement('strong');
    fort.textContent = `Dossier #${demande.id} — ${demande.etudiant_prenom} ${demande.etudiant_nom}`;
    titre.appendChild(fort);
    titre.appendChild(document.createTextNode(' '));

    const badge = document.createElement('span');
    badge.className = `statut-badge statut-${demande.statut}`;
    badge.textContent = demande.statut;
    titre.appendChild(badge);
    infos.appendChild(titre);

    const ent = document.createElement('p');
    ent.textContent = `Entreprise : ${demande.entreprise_nom} (${demande.entreprise_ville})`;
    infos.appendChild(ent);

    if (demande.motif_refus) {
      const motif = document.createElement('p');
      motif.textContent = `Motif de refus enregistré : ${demande.motif_refus}`;
      infos.appendChild(motif);
    }

    item.appendChild(infos);

    // Actions du responsable sur les demandes au statut SOUMISE
    if (etat.utilisateur.role === 'RESPONSABLE' && demande.statut === 'SOUMISE') {
      const actions = document.createElement('div');
      actions.className = 'actions-ligne';

      const btnValider = document.createElement('button');
      btnValider.type = 'button';
      btnValider.className = 'btn btn-succes';
      btnValider.textContent = 'Valider';
      btnValider.addEventListener('click', () => modifierStatutDemande(demande.id, 'VALIDEE', null));

      const btnRefuser = document.createElement('button');
      btnRefuser.type = 'button';
      btnRefuser.className = 'btn btn-danger';
      btnRefuser.textContent = 'Refuser';
      btnRefuser.addEventListener('click', () => ouvrirPanneauRefus(demande.id));

      actions.appendChild(btnValider);
      actions.appendChild(btnRefuser);
      item.appendChild(actions);
    }

    conteneur.appendChild(item);
  });
}

function ouvrirPanneauRefus(demandeId) {
  effacerErreursChamps();
  document.getElementById('refus-demande-id').value = String(demandeId);
  document.getElementById('motif').value = '';
  document.getElementById('titre-panneau-refus').textContent = `Refuser la demande #${demandeId}`;
  const panneau = document.getElementById('panneau-refus');
  panneau.hidden = false;
  document.getElementById('motif').focus();
}

async function modifierStatutDemande(id, statut, motif) {
  masquerAlerte();
  const res = await apiFetch(`/api/demandes/${id}/statut`, {
    method: 'PATCH',
    body: JSON.stringify({ statut, motif }),
  });

  if (!res) return;

  if (!res.ok) {
    if (res.status === 400 && statut === 'REFUSEE') {
      afficherErreurChamp('motif', res.data.message || 'Motif obligatoire.');
    } else if (res.status === 500) {
      afficherAlerte('erreur', 'Erreur technique momentanée. Veuillez réessayer plus tard.');
    } else {
      afficherAlerte('erreur', res.data.message || 'Action impossible.');
    }
    return;
  }

  document.getElementById('panneau-refus').hidden = true;
  afficherAlerte('succes', `Statut du dossier #${id} mis à jour : ${res.data.statut}.`);

  if (etat.utilisateur.role === 'ETUDIANT') {
    await chargerDemandesEtudiant();
  } else {
    await chargerDemandesResponsable();
  }
}

// Initialisation des écouteurs d'événements
document.addEventListener('DOMContentLoaded', async () => {
  // Vérifier si une session est déjà active (persistance au rafraîchissement)
  const sessionRes = await apiFetch('/api/moi', { method: 'GET' });
  if (sessionRes && sessionRes.ok && sessionRes.data.utilisateur) {
    await afficherEspaceUtilisateur(sessionRes.data.utilisateur);
  } else {
    basculerVueConnexion();
  }

  // Soumission formulaire de connexion
  document.getElementById('form-connexion').addEventListener('submit', async (e) => {
    e.preventDefault();
    masquerAlerte();
    effacerErreursChamps();

    const email = document.getElementById('email').value.trim();
    const motDePasse = document.getElementById('motDePasse').value;

    let valide = true;
    if (!email) {
      afficherErreurChamp('email', 'Veuillez saisir votre adresse email.');
      valide = false;
    }
    if (!motDePasse) {
      afficherErreurChamp('motDePasse', 'Veuillez saisir votre mot de passe.');
      valide = false;
    }
    if (!valide) return;

    const res = await apiFetch('/api/connexion', {
      method: 'POST',
      body: JSON.stringify({ email, motDePasse }),
    });

    if (!res) return;

    if (!res.ok) {
      if (res.status >= 500) {
        afficherAlerte('erreur', 'Service momentanément indisponible.');
      } else {
        afficherAlerte('erreur', res.data.message || 'Échec de la connexion.');
      }
      return;
    }

    await afficherEspaceUtilisateur(res.data.utilisateur);
  });

  // Déconnexion
  document.getElementById('btn-deconnexion').addEventListener('click', async () => {
    await apiFetch('/api/deconnexion', { method: 'POST' });
    etat.utilisateur = null;
    masquerAlerte();
    basculerVueConnexion();
  });

  // Soumission formulaire de dépôt de demande (étudiant)
  document.getElementById('form-demande').addEventListener('submit', async (e) => {
    e.preventDefault();
    masquerAlerte();
    effacerErreursChamps();

    const entrepriseId = document.getElementById('entrepriseId').value;
    const soumettre = document.getElementById('soumettre-directement').checked;

    if (!entrepriseId) {
      afficherErreurChamp('entrepriseId', 'Veuillez sélectionner une entreprise dans la liste.');
      return;
    }

    const res = await apiFetch('/api/demandes', {
      method: 'POST',
      body: JSON.stringify({
        entrepriseId: Number(entrepriseId),
        soumettre,
      }),
    });

    if (!res) return;

    if (!res.ok) {
      if (res.status === 400) {
        afficherErreurChamp('entrepriseId', res.data.message || 'Données invalides.');
      } else if (res.status >= 500) {
        afficherAlerte('erreur', 'Une erreur serveur est survenue lors de l\'enregistrement.');
      } else {
        afficherAlerte('erreur', res.data.message || 'Impossible de créer la demande.');
      }
      return;
    }

    document.getElementById('form-demande').reset();
    document.getElementById('soumettre-directement').checked = true;
    afficherAlerte('succes', `Demande #${res.data.id} enregistrée (${res.data.statut}).`);
    await chargerDemandesEtudiant();
  });

  // Soumission formulaire de refus motivé (responsable)
  document.getElementById('form-refus').addEventListener('submit', async (e) => {
    e.preventDefault();
    effacerErreursChamps();

    const id = Number(document.getElementById('refus-demande-id').value);
    const motif = document.getElementById('motif').value;

    await modifierStatutDemande(id, 'REFUSEE', motif);
  });

  document.getElementById('btn-annuler-refus').addEventListener('click', () => {
    document.getElementById('panneau-refus').hidden = true;
    effacerErreursChamps();
  });

  document.getElementById('btn-rafraichir-etudiant').addEventListener('click', chargerDemandesEtudiant);
  document.getElementById('btn-rafraichir-responsable').addEventListener('click', chargerDemandesResponsable);
});
