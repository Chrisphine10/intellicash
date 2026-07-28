package ke.co.intellicash.android.data.model

import kotlinx.serialization.Serializable

@Serializable
data class ApiEnvelope<T>(
    val data: T,
    val meta: Map<String, String>? = null
)

@Serializable
data class ApiError(
    val error: ErrorBody
)

@Serializable
data class ErrorBody(
    val code: String,
    val message: String
)

@Serializable
data class LoginRequest(
    val email: String,
    val password: String
)

@Serializable
data class LoginResponse(
    val id: String,
    val name: String,
    val email: String,
    val role: String,
    val permissions: List<String>,
    val avatarUrl: String? = null,
    val languagePreference: String = "ENGLISH",
    val partnerId: String? = null,
    val groupId: String? = null,
    val memberId: String? = null
)

@Serializable
data class User(
    val id: String,
    val name: String,
    val email: String,
    val role: String,
    val status: String = "ACTIVE",
    val permissions: List<String> = emptyList(),
    val avatarUrl: String? = null,
    val languagePreference: String = "ENGLISH",
    val partnerId: String? = null,
    val groupId: String? = null,
    val memberId: String? = null,
    val partner: PartnerRef? = null,
    val group: GroupRef? = null,
    val member: MemberRef? = null
)

@Serializable
data class PartnerRef(val id: String, val name: String)

@Serializable
data class GroupRef(val id: String, val name: String, val code: String)

@Serializable
data class MemberRef(val id: String, val fullName: String, val phone: String)

@Serializable
data class Group(
    val id: String,
    val name: String,
    val code: String,
    val phase: String,
    val county: String,
    val subCounty: String? = null,
    val location: String? = null,
    val meetingDay: String? = null,
    val shareValueCents: Int = 50000,
    val cycleNumber: Int = 1,
    val memberCount: Int = 0,
    val programmeId: String? = null,
    val villageAgentId: String? = null
)

@Serializable
data class Member(
    val id: String,
    val groupId: String,
    val fullName: String,
    val phone: String,
    val role: String = "MEMBER",
    val kycStatus: String = "PENDING",
    val status: String = "ACTIVE"
)

@Serializable
data class Meeting(
    val id: String,
    val groupId: String,
    val title: String,
    val status: String,
    val scheduledAt: String,
    val openedAt: String? = null,
    val closedAt: String? = null,
    val unlockStatus: String = "PENDING",
    val transactionTotal: Int = 0
)

@Serializable
data class LedgerEntry(
    val id: String,
    val groupId: String,
    val memberId: String? = null,
    val meetingId: String? = null,
    val type: String,
    val amountCents: Int,
    val currency: String = "KES",
    val direction: String,
    val description: String,
    val createdAt: String
)

@Serializable
data class FundAccount(
    val id: String,
    val groupId: String,
    val type: String,
    val balanceCents: Int = 0,
    val currency: String = "KES"
)

@Serializable
data class Notification(
    val id: String,
    val title: String,
    val body: String,
    val type: String = "INFO",
    val href: String? = null,
    val readAt: String? = null,
    val createdAt: String
)

@Serializable
data class PortfolioSummary(
    val groups: Int,
    val members: Int,
    val activeMeetings: Int,
    val totalSavingsCents: Long,
    val repaymentRate: Double,
    val averageCreditScore: Double,
    val phaseDistribution: Map<String, Int> = emptyMap(),
    val integrationConfigured: Int = 0,
    val integrationTotal: Int = 0
)

@Serializable
data class MeetingStep(
    val id: String,
    val meetingId: String,
    val step: String,
    val name: String,
    val status: String = "PENDING",
    val completedAt: String? = null
)

@Serializable
data class Attendance(
    val id: String,
    val meetingId: String,
    val memberId: String,
    val status: String,
    val memberName: String? = null
)

@Serializable
data class Vote(
    val id: String,
    val groupId: String,
    val resolutionType: String,
    val motion: String,
    val result: String,
    val yesCount: Int,
    val noCount: Int,
    val abstainCount: Int,
    val createdAt: String
)
