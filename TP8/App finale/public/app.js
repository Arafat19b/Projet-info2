'use strict';

const etat = { utilisateur: null, encadreursCache: [] };

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

async function apiFetch(url, options = {}) {
  const reponse = await fetch(url, {
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  });
  if (reponse.status === 401 && url !== '/api/connexion' && url !== '/api/moi') {
    etat.utilisateur = null;
    basculerVueConnexion();
    afficherAlerte('erreur', 'Session expirée. Veuillez vous reconnecter.');
    return null;
  }
  let donnees = null;
  try { donnees = await reponse.json(); } catch { donnees = { message: 'Erreur JSON' }; }
  return { ok: reponse.ok, status: reponse.status, data: donnees };
}

function basculerVueConnexion() {
  document.getElementById('vue-connexion').hidden = false;
  document.getElementById('vue-etudiant').hidden = true;
  document.getElementById('vue-responsable').hidden = true;
  document.getElementById('vue-encadreur').hidden = true;
  document.getElementById('barre-utilisateur').hidden = true;
}

async function afficherEspaceUtilisateur(utilisateur) {
  etat.utilisateur = utilisateur;
  masquerAlerte();
  document.getElementById('vue-connexion').hidden = true;
  document.getElementById('barre-utilisateur').hidden = false;
  document.getElementById('info-utilisateur').textContent = `${utilisateur.prenom} ${utilisateur.nom} (${utilisateur.role})`;

  if (utilisateur.role === 'ETUDIANT') {
    document.getElementById('vue-etudiant').hidden = false;
    document.getElementById('vue-responsable').hidden = true;
    document.getElementById('vue-encadreur').hidden = true;
    await chargerEntreprises();
    await chargerDemandesEtudiant();
  } else if (utilisateur.role === 'ENCADREUR') {
    document.getElementById('vue-etudiant').hidden = true;
    document.getElementById('vue-responsable').hidden = true;
    document.getElementById('vue-encadreur').hidden = false;
    await chargerDemandesEncadreur();
  } else {
    document.getElementById('vue-etudiant').hidden = true;
    document.getElementById('vue-responsable').hidden = false;
    document.getElementById('vue-encadreur').hidden = true;
    await chargerEncadreursCache();
    await chargerDemandesResponsable();
  }
}

async function chargerEntreprises() {
  const sel = document.getElementById('entrepriseId');
  const res = await apiFetch('/api/entreprises');
  if (!res || !res.ok) return;
  sel.innerHTML = '<option value="">-- Choisir une entreprise --</option>';
  res.data.forEach(ent => {
    const opt = document.createElement('option');
    opt.value = String(ent.id);
    opt.textContent = `${ent.nom} (${ent.ville})`;
    sel.appendChild(opt);
  });
}

async function chargerEncadreursCache() {
  const res = await apiFetch('/api/encadreurs');
  if (res && res.ok) { etat.encadreursCache = res.data; }
}

async function chargerDemandesEtudiant() {
  const conteneur = document.getElementById('liste-demandes-etudiant');
  const res = await apiFetch('/api/demandes');
  if (!res || !res.ok) return;
  conteneur.innerHTML = '';
  if (res.data.length === 0) {
    conteneur.textContent = 'Aucune demande.';
    return;
  }
  res.data.forEach(d => {
    const item = document.createElement('article');
    item.className = 'dossier-item';
    const inf = document.createElement('div');
    inf.className = 'dossier-infos';
    const p = document.createElement('p');
    p.appendChild(document.createTextNode(`Demande #${d.id} — ${d.entreprise_nom} `));
    const badge = document.createElement('span');
    badge.className = `statut-badge statut-${d.statut}`;
    badge.textContent = d.statut;
    p.appendChild(badge);
    inf.appendChild(p);

    if (d.encadreur_nom) {
      const pEnc = document.createElement('p');
      pEnc.textContent = `Encadreur affecté : ${d.encadreur_prenom} ${d.encadreur_nom}`;
      inf.appendChild(pEnc);
    }
    if (d.soutenance_date) {
      const pSout = document.createElement('p');
      pSout.textContent = `Soutenance : ${d.soutenance_date} en salle ${d.soutenance_salle}` + (d.soutenance_note !== null ? ` — Note : ${d.soutenance_note}/20` : '');
      inf.appendChild(pSout);
    }
    item.appendChild(inf);

    const act = document.createElement('div');
    act.className = 'actions-ligne';
    if (d.statut === 'BROUILLON') {
      const btn = document.createElement('button');
      btn.className = 'btn btn-primaire';
      btn.textContent = 'Soumettre';
      btn.addEventListener('click', () => modifierStatut(d.id, 'SOUMISE', null));
      act.appendChild(btn);
    }
    item.appendChild(act);
    conteneur.appendChild(item);
  });
}

async function chargerDemandesResponsable() {
  const conteneur = document.getElementById('liste-demandes-responsable');
  const res = await apiFetch('/api/demandes');
  if (!res || !res.ok) return;
  conteneur.innerHTML = '';
  if (res.data.length === 0) {
    conteneur.textContent = 'Aucun dossier.';
    return;
  }
  res.data.forEach(d => {
    const item = document.createElement('article');
    item.className = 'dossier-item';
    const inf = document.createElement('div');
    inf.className = 'dossier-infos';
    const p = document.createElement('p');
    p.appendChild(document.createTextNode(`Dossier #${d.id} — ${d.etudiant_prenom} ${d.etudiant_nom} (${d.entreprise_nom}) `));
    const badge = document.createElement('span');
    badge.className = `statut-badge statut-${d.statut}`;
    badge.textContent = d.statut;
    p.appendChild(badge);
    inf.appendChild(p);

    const pEnc = document.createElement('p');
    pEnc.textContent = d.encadreur_nom ? `Encadreur : ${d.encadreur_prenom} ${d.encadreur_nom}` : 'Encadreur : Non affecté';
    inf.appendChild(pEnc);

    if (d.soutenance_date) {
      const pSout = document.createElement('p');
      pSout.textContent = `Soutenance : ${d.soutenance_date} (Salle ${d.soutenance_salle})` + (d.soutenance_note !== null ? ` — Note : ${d.soutenance_note}/20` : '');
      inf.appendChild(pSout);
    }
    item.appendChild(inf);

    const act = document.createElement('div');
    act.className = 'actions-ligne';

    if (d.statut === 'SOUMISE') {
      const bVal = document.createElement('button');
      bVal.className = 'btn btn-succes';
      bVal.textContent = 'Valider';
      bVal.addEventListener('click', () => modifierStatut(d.id, 'VALIDEE', null));
      const bRef = document.createElement('button');
      bRef.className = 'btn btn-danger';
      bRef.textContent = 'Refuser';
      bRef.addEventListener('click', () => ouvrirPanneauRefus(d.id));
      act.appendChild(bVal);
      act.appendChild(bRef);
    } else if (d.statut === 'VALIDEE') {
      // Sélecteur d'encadreur
      const selectEnc = document.createElement('select');
      const optDef = document.createElement('option');
      optDef.value = '';
      optDef.textContent = '-- Assigner un encadreur --';
      selectEnc.appendChild(optDef);
      etat.encadreursCache.forEach(en => {
        const o = document.createElement('option');
        o.value = String(en.id);
        o.textContent = `${en.prenom} ${en.nom} (${en.grade})`;
        if (Number(d.encadreur_id) === en.id) { o.selected = true; }
        selectEnc.appendChild(o);
      });
      selectEnc.addEventListener('change', (e) => assignerEncadreur(d.id, e.target.value));
      act.appendChild(selectEnc);

      const btnSout = document.createElement('button');
      btnSout.className = 'btn btn-primaire';
      btnSout.textContent = d.soutenance_date ? 'Modifier Soutenance' : 'Programmer Soutenance';
      btnSout.addEventListener('click', () => ouvrirPanneauSoutenance(d.id));
      act.appendChild(btnSout);
    }
    item.appendChild(act);
    conteneur.appendChild(item);
  });
}

async function chargerDemandesEncadreur() {
  const conteneur = document.getElementById('liste-demandes-encadreur');
  const res = await apiFetch('/api/demandes');
  if (!res || !res.ok) return;
  conteneur.innerHTML = '';
  if (res.data.length === 0) {
    conteneur.textContent = 'Aucun dossier encadré.';
    return;
  }
  res.data.forEach(d => {
    const item = document.createElement('article');
    item.className = 'dossier-item';
    const inf = document.createElement('div');
    inf.className = 'dossier-infos';
    const p = document.createElement('p');
    p.textContent = `Dossier #${d.id} — Étudiant : ${d.etudiant_prenom} ${d.etudiant_nom} (${d.entreprise_nom})`;
    inf.appendChild(p);

    if (d.soutenance_date) {
      const pSout = document.createElement('p');
      pSout.textContent = `Soutenance : ${d.soutenance_date} (Salle ${d.soutenance_salle})`;
      inf.appendChild(pSout);
    }
    item.appendChild(inf);

    const act = document.createElement('div');
    act.className = 'actions-ligne';
    if (d.soutenance_date) {
      const inputNote = document.createElement('input');
      inputNote.type = 'number';
      inputNote.min = '0';
      inputNote.max = '20';
      inputNote.step = '0.25';
      inputNote.placeholder = 'Note /20';
      inputNote.value = d.soutenance_note !== null ? String(d.soutenance_note) : '';
      inputNote.style.width = '90px';

      const btnNoter = document.createElement('button');
      btnNoter.className = 'btn btn-succes';
      btnNoter.textContent = 'Enregistrer Note';
      btnNoter.addEventListener('click', () => noterSoutenance(d.id, inputNote.value));

      act.appendChild(inputNote);
      act.appendChild(btnNoter);
    } else {
      const pAtt = document.createElement('span');
      pAtt.textContent = 'En attente de programmation de soutenance';
      act.appendChild(pAtt);
    }
    item.appendChild(act);
    conteneur.appendChild(item);
  });
}

function ouvrirPanneauRefus(id) {
  document.getElementById('refus-demande-id').value = String(id);
  document.getElementById('motif').value = '';
  document.getElementById('panneau-refus').hidden = false;
  document.getElementById('motif').focus();
}

function ouvrirPanneauSoutenance(id) {
  document.getElementById('soutenance-demande-id').value = String(id);
  document.getElementById('date-soutenance').value = '';
  document.getElementById('salle-soutenance').value = '';
  document.getElementById('panneau-soutenance').hidden = false;
}

async function modifierStatut(id, statut, motif) {
  const res = await apiFetch(`/api/demandes/${id}/statut`, {
    method: 'PATCH',
    body: JSON.stringify({ statut, motif }),
  });
  if (!res) return;
  if (!res.ok) {
    afficherAlerte('erreur', res.data.message || 'Action impossible.');
    return;
  }
  document.getElementById('panneau-refus').hidden = true;
  afficherAlerte('succes', `Statut mis à jour : ${res.data.statut}`);
  rafraichirCourant();
}

async function assignerEncadreur(demandeId, encadreurId) {
  if (!encadreurId) return;
  const res = await apiFetch(`/api/demandes/${demandeId}/encadreur`, {
    method: 'PATCH',
    body: JSON.stringify({ encadreurId: Number(encadreurId) }),
  });
  if (!res || !res.ok) {
    afficherAlerte('erreur', res?.data?.message || 'Erreur affectation encadreur.');
    return;
  }
  afficherAlerte('succes', 'Encadreur affecté avec succès.');
  rafraichirCourant();
}

async function noterSoutenance(demandeId, note) {
  const res = await apiFetch(`/api/demandes/${demandeId}/note`, {
    method: 'PATCH',
    body: JSON.stringify({ note: Number(note) }),
  });
  if (!res || !res.ok) {
    afficherAlerte('erreur', res?.data?.message || 'Erreur notation.');
    return;
  }
  afficherAlerte('succes', `Note de soutenance enregistrée : ${res.data.note}/20`);
  rafraichirCourant();
}

function rafraichirCourant() {
  if (!etat.utilisateur) return;
  if (etat.utilisateur.role === 'ETUDIANT') chargerDemandesEtudiant();
  else if (etat.utilisateur.role === 'ENCADREUR') chargerDemandesEncadreur();
  else chargerDemandesResponsable();
}

document.addEventListener('DOMContentLoaded', async () => {
  const moi = await apiFetch('/api/moi');
  if (moi && moi.ok && moi.data.utilisateur) {
    await afficherEspaceUtilisateur(moi.data.utilisateur);
  } else {
    basculerVueConnexion();
  }

  document.getElementById('form-connexion').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value.trim();
    const motDePasse = document.getElementById('motDePasse').value;
    const res = await apiFetch('/api/connexion', {
      method: 'POST',
      body: JSON.stringify({ email, motDePasse }),
    });
    if (!res || !res.ok) {
      afficherAlerte('erreur', res?.data?.message || 'Échec connexion.');
      return;
    }
    await afficherEspaceUtilisateur(res.data.utilisateur);
  });

  document.getElementById('btn-deconnexion').addEventListener('click', async () => {
    await apiFetch('/api/deconnexion', { method: 'POST' });
    etat.utilisateur = null;
    basculerVueConnexion();
  });

  document.getElementById('form-demande').addEventListener('submit', async (e) => {
    e.preventDefault();
    const entrepriseId = document.getElementById('entrepriseId').value;
    const soumettre = document.getElementById('soumettre-directement').checked;
    if (!entrepriseId) {
      afficherAlerte('erreur', 'Veuillez choisir une entreprise.');
      return;
    }
    const res = await apiFetch('/api/demandes', {
      method: 'POST',
      body: JSON.stringify({ entrepriseId: Number(entrepriseId), soumettre }),
    });
    if (!res || !res.ok) {
      afficherAlerte('erreur', res?.data?.message || 'Erreur création.');
      return;
    }
    document.getElementById('form-demande').reset();
    afficherAlerte('succes', `Demande #${res.data.id} créée (${res.data.statut}).`);
    chargerDemandesEtudiant();
  });

  document.getElementById('form-refus').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = Number(document.getElementById('refus-demande-id').value);
    const motif = document.getElementById('motif').value;
    await modifierStatut(id, 'REFUSEE', motif);
  });

  document.getElementById('btn-annuler-refus').addEventListener('click', () => {
    document.getElementById('panneau-refus').hidden = true;
  });

  document.getElementById('form-soutenance').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = Number(document.getElementById('soutenance-demande-id').value);
    const dateHeure = document.getElementById('date-soutenance').value;
    const salle = document.getElementById('salle-soutenance').value;

    const res = await apiFetch(`/api/demandes/${id}/soutenance`, {
      method: 'POST',
      body: JSON.stringify({ dateHeure: dateHeure.replace('T', ' ') + ':00', salle }),
    });
    if (!res || !res.ok) {
      afficherAlerte('erreur', res?.data?.message || 'Erreur programmation soutenance.');
      return;
    }
    document.getElementById('panneau-soutenance').hidden = true;
    afficherAlerte('succes', 'Soutenance programmée avec succès.');
    rafraichirCourant();
  });

  document.getElementById('btn-annuler-soutenance').addEventListener('click', () => {
    document.getElementById('panneau-soutenance').hidden = true;
  });

  document.getElementById('btn-rafraichir-etudiant')?.addEventListener('click', chargerDemandesEtudiant);
  document.getElementById('btn-rafraichir-responsable')?.addEventListener('click', chargerDemandesResponsable);
  document.getElementById('btn-rafraichir-encadreur')?.addEventListener('click', chargerDemandesEncadreur);
});
