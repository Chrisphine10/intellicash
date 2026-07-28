package ke.co.intellicash.android.ui.groups

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import ke.co.intellicash.android.data.model.*
import ke.co.intellicash.android.data.repository.ApiResult
import ke.co.intellicash.android.data.repository.IntelliCashRepository
import ke.co.intellicash.android.ui.components.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class GroupDetailState(
    val isLoading: Boolean = true,
    val group: Group? = null,
    val members: List<Member> = emptyList(),
    val meetings: List<Meeting> = emptyList(),
    val funds: List<FundAccount> = emptyList(),
    val error: String? = null
)

@HiltViewModel
class GroupDetailViewModel @Inject constructor(
    private val repository: IntelliCashRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {
    private val groupId: String = savedStateHandle["groupId"] ?: ""
    private val _state = MutableStateFlow(GroupDetailState())
    val state = _state.asStateFlow()

    init { load() }

    fun load() {
        viewModelScope.launch {
            _state.value = GroupDetailState(isLoading = true)
            val groupResult = repository.getGroup(groupId)
            val membersResult = repository.getGroupMembers(groupId)
            val meetingsResult = repository.getGroupMeetings(groupId)
            val fundsResult = repository.getGroupFunds(groupId)

            _state.value = GroupDetailState(
                isLoading = false,
                group = (groupResult as? ApiResult.Success)?.data,
                members = (membersResult as? ApiResult.Success)?.data ?: emptyList(),
                meetings = (meetingsResult as? ApiResult.Success)?.data ?: emptyList(),
                funds = (fundsResult as? ApiResult.Success)?.data ?: emptyList(),
                error = if (groupResult is ApiResult.Error) groupResult.message else null
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GroupDetailScreen(
    onBack: () -> Unit,
    onMeetingClick: (groupId: String, meetingId: String) -> Unit,
    onLedgerClick: (groupId: String) -> Unit,
    viewModel: GroupDetailViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(state.group?.name ?: "Group") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        when {
            state.isLoading -> LoadingScreen(modifier = Modifier.padding(padding))
            state.error != null -> ErrorScreen(state.error!!, viewModel::load, Modifier.padding(padding))
            else -> {
                val group = state.group!!
                LazyColumn(
                    modifier = Modifier.padding(padding),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    item {
                        Card(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Text("Group Info", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                                Spacer(modifier = Modifier.height(8.dp))
                                InfoRow("Code", group.code)
                                InfoRow("County", group.county)
                                InfoRow("Phase", group.phase.replace("_", " "))
                                InfoRow("Cycle", group.cycleNumber.toString())
                                InfoRow("Share Value", formatKes(group.shareValueCents))
                                group.meetingDay?.let { InfoRow("Meeting Day", it) }
                            }
                        }
                    }

                    if (state.funds.isNotEmpty()) {
                        item {
                            Text("Fund Accounts", style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
                        }
                        item {
                            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                                state.funds.take(3).forEach { fund ->
                                    StatCard(
                                        label = fund.type.replace("_", " "),
                                        value = formatKes(fund.balanceCents),
                                        modifier = Modifier.weight(1f)
                                    )
                                }
                            }
                        }
                    }

                    item {
                        ElevatedCard(
                            onClick = { onLedgerClick(group.id) },
                            modifier = Modifier.fillMaxWidth()
                        ) {
                            ListItem(
                                headlineContent = { Text("View Ledger") },
                                supportingContent = { Text("Transaction history") },
                                leadingContent = { Icon(Icons.Default.Receipt, null, tint = MaterialTheme.colorScheme.primary) },
                                trailingContent = { Icon(Icons.Default.ChevronRight, null) }
                            )
                        }
                    }

                    item {
                        Text(
                            "Members (${state.members.size})",
                            style = MaterialTheme.typography.titleMedium,
                            fontWeight = FontWeight.Bold
                        )
                    }
                    items(state.members, key = { it.id }) { member ->
                        ListItem(
                            headlineContent = { Text(member.fullName) },
                            supportingContent = { Text("${member.role} | ${member.status}") },
                            leadingContent = {
                                Icon(Icons.Default.Person, null, tint = MaterialTheme.colorScheme.primary)
                            }
                        )
                    }

                    if (state.meetings.isNotEmpty()) {
                        item {
                            Text(
                                "Recent Meetings",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        items(state.meetings.take(5), key = { it.id }) { meeting ->
                            ElevatedCard(
                                onClick = { onMeetingClick(group.id, meeting.id) },
                                modifier = Modifier.fillMaxWidth()
                            ) {
                                ListItem(
                                    headlineContent = { Text(meeting.title) },
                                    supportingContent = {
                                        Text("${meeting.status} | ${meeting.scheduledAt.take(10)}")
                                    },
                                    leadingContent = {
                                        Icon(
                                            Icons.Default.Event,
                                            null,
                                            tint = when (meeting.status) {
                                                "IN_PROGRESS" -> MaterialTheme.colorScheme.primary
                                                "SEALED" -> MaterialTheme.colorScheme.onSurfaceVariant
                                                else -> MaterialTheme.colorScheme.secondary
                                            }
                                        )
                                    }
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun InfoRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, style = MaterialTheme.typography.bodyMedium, fontWeight = FontWeight.Medium)
    }
}
