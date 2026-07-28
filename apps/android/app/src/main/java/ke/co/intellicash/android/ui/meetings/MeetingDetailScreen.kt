package ke.co.intellicash.android.ui.meetings

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import ke.co.intellicash.android.data.model.Meeting
import ke.co.intellicash.android.data.model.MeetingStep
import ke.co.intellicash.android.data.repository.ApiResult
import ke.co.intellicash.android.data.repository.IntelliCashRepository
import ke.co.intellicash.android.ui.components.ErrorScreen
import ke.co.intellicash.android.ui.components.LoadingScreen
import ke.co.intellicash.android.ui.components.formatKes
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

data class MeetingDetailState(
    val isLoading: Boolean = true,
    val meeting: Meeting? = null,
    val steps: List<MeetingStep> = emptyList(),
    val error: String? = null
)

@HiltViewModel
class MeetingDetailViewModel @Inject constructor(
    private val repository: IntelliCashRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {
    private val groupId: String = savedStateHandle["groupId"] ?: ""
    private val meetingId: String = savedStateHandle["meetingId"] ?: ""
    private val _state = MutableStateFlow(MeetingDetailState())
    val state = _state.asStateFlow()

    init { load() }

    fun load() {
        viewModelScope.launch {
            _state.value = MeetingDetailState(isLoading = true)
            val meetingResult = repository.getMeeting(groupId, meetingId)
            val stepsResult = repository.getMeetingSteps(groupId, meetingId)

            _state.value = MeetingDetailState(
                isLoading = false,
                meeting = (meetingResult as? ApiResult.Success)?.data,
                steps = (stepsResult as? ApiResult.Success)?.data ?: emptyList(),
                error = if (meetingResult is ApiResult.Error) meetingResult.message else null
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MeetingDetailScreen(
    onBack: () -> Unit,
    viewModel: MeetingDetailViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(state.meeting?.title ?: "Meeting") },
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
                val meeting = state.meeting!!
                LazyColumn(
                    modifier = Modifier.padding(padding),
                    contentPadding = PaddingValues(16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    item {
                        Card(modifier = Modifier.fillMaxWidth()) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    StatusChip(meeting.status)
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text(
                                        meeting.title,
                                        style = MaterialTheme.typography.titleLarge,
                                        fontWeight = FontWeight.Bold
                                    )
                                }
                                Spacer(modifier = Modifier.height(12.dp))
                                Text("Scheduled: ${meeting.scheduledAt.take(16).replace("T", " ")}")
                                meeting.openedAt?.let {
                                    Text("Opened: ${it.take(16).replace("T", " ")}")
                                }
                                meeting.closedAt?.let {
                                    Text("Closed: ${it.take(16).replace("T", " ")}")
                                }
                                Text("Transaction Total: ${formatKes(meeting.transactionTotal)}")
                                Text("Unlock: ${meeting.unlockStatus}")
                            }
                        }
                    }

                    if (state.steps.isNotEmpty()) {
                        item {
                            Text(
                                "Meeting Steps",
                                style = MaterialTheme.typography.titleMedium,
                                fontWeight = FontWeight.Bold
                            )
                        }
                        items(state.steps) { step ->
                            ListItem(
                                headlineContent = {
                                    Text(step.name.replace("_", " "))
                                },
                                supportingContent = {
                                    step.completedAt?.let {
                                        Text("Completed: ${it.take(16).replace("T", " ")}")
                                    }
                                },
                                leadingContent = {
                                    Icon(
                                        when (step.status) {
                                            "COMPLETED" -> Icons.Default.CheckCircle
                                            "IN_PROGRESS" -> Icons.Default.PlayCircle
                                            else -> Icons.Default.RadioButtonUnchecked
                                        },
                                        contentDescription = null,
                                        tint = when (step.status) {
                                            "COMPLETED" -> MaterialTheme.colorScheme.primary
                                            "IN_PROGRESS" -> MaterialTheme.colorScheme.secondary
                                            else -> MaterialTheme.colorScheme.onSurfaceVariant
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

@Composable
private fun StatusChip(status: String) {
    val color = when (status) {
        "IN_PROGRESS" -> MaterialTheme.colorScheme.primary
        "SEALED" -> MaterialTheme.colorScheme.onSurfaceVariant
        "SCHEDULED" -> MaterialTheme.colorScheme.secondary
        else -> MaterialTheme.colorScheme.tertiary
    }
    AssistChip(
        onClick = {},
        label = { Text(status.replace("_", " "), style = MaterialTheme.typography.labelSmall) },
        colors = AssistChipDefaults.assistChipColors(labelColor = color)
    )
}
