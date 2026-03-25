import fs from 'fs'

const data = JSON.parse(fs.readFileSync('/Users/paypanha/SMEFrontend/.data/sme_frontend_db.json', 'utf-8'));
const map = data.assessments.map(a => a.sme_id);
console.log("Unique SME IDs in DB:", [...new Set(map)]);
