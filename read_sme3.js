import fs from 'fs'

const data = JSON.parse(fs.readFileSync('/Users/paypanha/SMEFrontend/.data/sme_frontend_db.json', 'utf-8'));
const sme3Assessments = data.assessments.filter(a => a.sme_id == 3);
console.log("SME 3 Assessments:", JSON.stringify(sme3Assessments, null, 2));

const responses = data.responses.filter(r => r.assessment_id === sme3Assessments[0]?.id);
console.log("SME 3 Responses:", JSON.stringify(responses, null, 2));
