# Chapter I  
## The Problem and its Background

### 1.1 Project Context and Introduction

Access to higher education in the Philippines is often constrained by financial limitations, uneven access to information, and fragmented scholarship management processes. While many public and private institutions offer scholarships, students frequently struggle to discover, understand, and apply for opportunities that match their qualifications. Likewise, scholarship administrators face difficulties in handling large volumes of applications, verifying eligibility, and monitoring grantees using mostly manual, paper-based, or scattered digital processes.

At **[Name of Institution]**, the management of scholarships is typically done through printed forms, email submissions, and basic spreadsheets. This leads to redundant work, delays in screening, inconsistent records, and limited visibility into the overall scholarship landscape (e.g., number of applicants, profile of grantees, fund utilization, and performance monitoring). Students are often unaware of existing scholarships, application timelines, and requirements, resulting in missed opportunities or incomplete submissions.

To address these challenges, the **ScholarSeek: Scholarship Management and Recommendation System** is proposed as a web-based platform that centralizes scholarship information, streamlines application processing, and provides decision support tools for administrators. The system aims to connect qualified students with appropriate scholarship opportunities, automate repetitive administrative tasks, and provide data-driven insights for policy and decision making.

By digitalizing the end-to-end processfrom scholarship posting and application submission to evaluation, awarding, and monitoringScholarSeek seeks to improve efficiency, transparency, and accessibility in scholarship management, benefiting both students and the institution.

---

### 1.2 Statement of the Problem

Despite the availability of various scholarship programs at **[Name of Institution]**, the current management process faces several issues that hinder its effectiveness and reach.

#### 1.2.1 General Problem

How can **[Name of Institution]** improve the efficiency, accessibility, and accuracy of its scholarship management process to better match qualified students with appropriate scholarship opportunities?

#### 1.2.2 Specific Problems

Specifically, the study aims to address the following problems:

1. **Dispersed and inaccessible scholarship information**  
   Scholarship details (eligibility, requirements, deadlines) are not centralized, making it difficult for students to discover and compare opportunities.

2. **Manual and error-prone application processing**  
   Administrators rely on paper forms, email, or basic spreadsheets, which can lead to lost documents, duplicate entries, and slow evaluation.

3. **Limited decision support for screening and ranking applicants**  
   Evaluation is often based on manual checking of grades, income, and other criteria, which is time-consuming and prone to inconsistency.

4. **Lack of real-time monitoring and reporting**  
   There is no integrated system for generating up-to-date reports on number of applicants, status of applications, profile of scholars, and utilization of scholarship slots.

5. **Insufficient feedback and transparency for students**  
   Students have limited visibility into their application status, reasons for approval or rejection, and future scholarship opportunities.

These problems collectively demonstrate the need for an integrated scholarship management and recommendation system.

---

### 1.3 Objectives of the Study

The general objective of this study is to design and develop the **ScholarSeek** system that will digitalize and enhance the scholarship management process of **[Name of Institution]**.

Specifically, the study aims to:

1. **Develop a centralized web-based platform** that will store and display complete and updated information about available scholarship programs, including eligibility, requirements, benefits, and deadlines.

2. **Automate the scholarship application process** by providing online submission of applications and requirements, including verification of basic eligibility criteria (e.g., year level, program, GWA).

3. **Design an evaluation and screening module** that will assist administrators in ranking and filtering applicants based on predefined criteria such as academic performance, financial needs, and program/department.

4. **Provide reporting and analytics features** that generate summaries and visualizations (e.g., total applicants, approved/rejected, distribution by program/year level) to support decision making and policy formulation.

5. **Implement a student dashboard** where applicants can view scholarship announcements, track their application status, receive notifications, and review the history of their submitted applications.

---

### 1.4 Scope and Delimitations

#### 1.4.1 Scope

The **ScholarSeek** system will cover the following:

- **Target Users**
  - **Students** of **[Name of Institution]** who wish to apply for available scholarships.
  - **Staff/Scholarship Coordinators** who manage scholarship postings, evaluate applications, and generate reports.
  - **Administrators** (e.g., scholarship office head, dean, or registrar) who oversee the overall scholarship system and approvals.

- **Core Features**
  - User registration and login (students, staff, admin).
  - Student profile management (basic information, academic details, supporting documents).
  - Scholarship management (creation, update, activation/deactivation of scholarship offerings).
  - Online scholarship application form and requirements submission.
  - Application status tracking (pending, approved, rejected).
  - Filtering and sorting of applications by status, program, department, and other criteria.
  - Basic reporting features (counts, summaries, and simple visual statistics).
  - Notification prompts for important actions (e.g., application submission, status changes).

- **Technical Scope**
  - Web-based system accessible via modern web browsers (desktop/laptop).
  - Use of **PHP**, **MySQL**, **HTML/CSS/JavaScript**.
  - Deployed within the local network or institutional server environment (e.g., XAMPP during development).

#### 1.4.2 Delimitations

The study is delimited by the following:

- The system will focus on **institutional scholarships** managed by **[Name of Office/Unit]** and may not initially include external scholarship providers.
- The system will not handle **online payment, stipends release, or banking transactions**; financial disbursement will remain under existing institutional processes.
- The recommendation component will be **rule-based**, relying on defined criteria (e.g., course, GWA, income brackets) rather than advanced machine learning algorithms.
- The systems performance will be evaluated primarily in terms of **usability, efficiency, and accuracy of records**, rather than long-term impact on student retention or graduation rates.
- Access will be limited to **authorized users within the institution**; no public portal or mobile application is included in this initial scope.

---

### 1.5 Review of Related Literature and Studies

This section presents literature and prior works relevant to scholarship management systems, educational information systems, and web-based application platforms. It provides theoretical grounding and highlights the gap this study aims to address.

#### 1.5.1 Foreign Literature

**Web-Based Scholarship Management Systems**  
Several universities abroad have adopted online scholarship management portals to handle large volumes of applications and to improve transparency. These systems typically centralize scholarship announcements, automate application screening, and provide dashboards for administrators and students. Studies report that such systems reduce processing time, improve record accuracy, and increase student awareness of opportunities.

**Educational Decision Support Systems**  
Information systems in education increasingly incorporate decision support modules for assessing student performance, ranking applicants, and identifying at-risk students. These systems demonstrate the value of structured criteria and data analytics in guiding allocation of limited educational resources, including scholarships.

**User-Centered Design in Academic Portals**  
Research on web portals for students emphasizes the importance of usability, consistency, and accessibility. Systems designed with user-centered principles (e.g., intuitive navigation, clear feedback, mobile responsiveness) show higher adoption rates and better satisfaction, which is important for scholarship platforms that depend on student engagement.

#### 1.5.2 Local Literature

**Scholarship Administration in Philippine HEIs**  
Local studies describe scholarship administration in state universities and colleges as heavily dependent on manual documentation, with application forms processed via physical submission and evaluation conducted using printed records. These studies often highlight issues such as delays in releasing results, incomplete data, and duplication of effort among offices.

**Student Information and Academic Monitoring Systems**  
Several local institutions have implemented Student Information Systems (SIS) that maintain student profiles, grades, and enrollment records. While these systems improve data access and reporting, they rarely provide specific modules for scholarship management or automated scholarship recommendations based on student profiles.

**Web-Based Application Portals**  
Local projects on web-based enrollment or admission systems show improved efficiency and reduced queues in administrative offices. These works demonstrate the feasibility of implementing centralized, web-based platforms in the Philippine HEI context, but often do not extend to scholarship services.

#### 1.5.3 Synthesis and Research Gap

The reviewed literature and systems show that:

- Web-based platforms can significantly improve efficiency and transparency in handling academic processes.
- Decision support tools assist in objective and data-driven selection of qualified applicants.
- Local institutions often still rely on manual or semi-manual scholarship processes, even if other academic services are digitalized.

However, **there is a gap in integrated, scholarship-specific systems** that:

- Centralize scholarship information,
- Connect directly with student academic and profile data,
- Provide rule-based recommendation or filtering, and
- Offer both administrative tools and student-facing dashboards.

The **ScholarSeek** system addresses this gap by creating a dedicated, web-based scholarship management and recommendation platform tailored to the needs and context of **[Name of Institution]**.

---

### 1.6 Conceptual / Theoretical Framework

#### 1.6.1 Input-Process-Output (IPO) Model

The conceptual framework of this study is based on the **Input-Process-Output (IPO)** model.

- **Input**
  - Student data (personal information, academic records, program/department, year level).
  - Scholarship data (criteria, benefits, requirements, slots, deadlines).
  - Administrative policies (eligibility rules, prioritization criteria, evaluation rubrics).

- **Process**
  - Registration and authentication of users.
  - Encoding and management of scholarship offerings by staff/admin.
  - Submission of scholarship applications by students.
  - Automated checking of basic eligibility (e.g., GWA, program, year level).
  - Evaluation and ranking of applicants based on defined criteria.
  - Approval, rejection, and updating of application statuses.
  - Generation of reports and summaries for decision makers.

- **Output**
  - Approved and documented scholars with complete records.
  - Organized list of applicants and their status (pending, approved, rejected).
  - Consolidated reports and statistics (per scholarship, per department, per year level).
  - Improved accessibility of scholarship information and tracking for students and administrators.

#### 1.6.2 Software Development Life Cycle (SDLC)

The development of the system will follow the **[specify model: e.g., Waterfall, Iterative, or Agile]** Software Development Life Cycle, which consists of:

1. **Planning and Requirements Analysis**  Identifying user needs, existing problems, and desired features.
2. **System Design**  Creating data models, system architecture, and interface designs.
3. **Implementation**  Coding the application using the chosen technologies.
4. **Testing**  Conducting unit, integration, and user acceptance testing.
5. **Deployment**  Installing the system in a test or production environment.
6. **Maintenance**  Applying modifications and enhancements based on feedback.

This structured approach ensures that the system is systematically planned, developed, and evaluated.

---

### 1.7 Definition of Terms

For clarity, the following terms are defined as they are used in this study:

- **Scholarship**  
  A financial grant or benefit awarded to a student based on academic performance, financial need, or other criteria, intended to support educational expenses.

- **Scholar**  
  A student who has been officially granted a scholarship under the programs managed within the system.

- **Scholarship Management System**  
  A web-based information system designed to manage scholarship postings, applications, evaluation, and monitoring.

- **ScholarSeek**  
  The proposed web-based scholarship management and recommendation system developed in this study for **[Name of Institution]**.

- **Applicant**  
  A student who submits an application for a scholarship through the system.

- **Administrator (Admin)**  
  A user with higher-level access rights responsible for managing scholarships, approving applications, and overseeing the overall system.

- **Staff / Scholarship Coordinator**  
  An authorized personnel tasked with encoding scholarship details, screening applications, and preparing reports.

- **Student Dashboard**  
  The part of the system where students can view scholarships, submit applications, and track their application status.

- **Eligibility Criteria**  
  The conditions or requirements (e.g., GWA, enrollment status, program, income bracket) that a student must meet to qualify for a scholarship.

- **GWA (General Weighted Average)**  
  The numerical representation of a students overall academic performance, as defined by the grading system of **[Name of Institution]**.

- **Web-Based System**  
  A software application that can be accessed through a web browser over a network such as the internet or an institutional intranet.

- **Decision Support**  
  Features of the system that assist administrators in making informed decisions, such as filters, rankings, and analytical reports.
