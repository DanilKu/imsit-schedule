//
//  ScheduleListView.swift
//  imsitID
//

import SwiftUI

struct ScheduleListView: View {
    @EnvironmentObject var appState: AppState
    
    let fullDayNames = ["Понедельник", "Вторник", "Среда", "Четверг", "Пятница", "Суббота"]
    
    var body: some View {
        VStack(alignment: .leading, spacing: 20) {
            // Day name header
            HStack {
                Text(fullDayNames[appState.currentDay - 1])
                    .font(.system(size: 24, weight: .bold))
                    .foregroundColor(.white)
                Spacer()
            }
            
            let lessons = appState.getCurrentLessons()
            
            if lessons.isEmpty {
                EmptyScheduleView()
                    .transition(.opacity.combined(with: .scale(scale: 0.95)))
            } else {
                LazyVStack(spacing: 12) {
                    ForEach(lessons) { lesson in
                        LessonCard(lesson: lesson)
                            .transition(.asymmetric(
                                insertion: .move(edge: .bottom).combined(with: .opacity),
                                removal: .move(edge: .top).combined(with: .opacity)
                            ))
                    }
                }
            }
        }
    }
}

struct LessonCard: View {
    let lesson: Lesson
    @EnvironmentObject var appState: AppState
    
    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack {
                Label(lesson.timeRange, systemImage: "clock.fill")
                    .font(.system(size: 13, weight: .semibold))
                    .foregroundColor(.white.opacity(0.9))
                    .textCase(.uppercase)
                    .tracking(0.5)
                Spacer()
            }
            
            Text(lesson.subjectName)
                .font(.system(size: 18, weight: .bold))
                .foregroundColor(.white)
                .lineLimit(2)
                .fixedSize(horizontal: false, vertical: true)
            
            HStack(spacing: 10) {
                // Room badge
                HStack(spacing: 6) {
                    Image(systemName: "door.left.hand.open")
                        .font(.system(size: 11, weight: .semibold))
                    Text(lesson.roomNumber)
                        .font(.system(size: 13, weight: .semibold))
                }
                .foregroundColor(.white)
                .padding(.horizontal, 12)
                .padding(.vertical, 6)
                .background(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.2),
                            Color.white.opacity(0.1)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(
                            LinearGradient(
                                colors: [
                                    Color.white.opacity(0.3),
                                    Color.white.opacity(0.1)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 1
                        )
                )
                
                // Teacher/Group badge
                HStack(spacing: 6) {
                    if appState.viewMode == .teacher, let groups = lesson.groups, !groups.isEmpty {
                        Image(systemName: "person.3.fill")
                            .font(.system(size: 11, weight: .semibold))
                        Text(groups.joined(separator: ", "))
                            .font(.system(size: 13, weight: .semibold))
                            .lineLimit(1)
                    } else if appState.viewMode == .teacher, let groupName = lesson.groupName, !groupName.isEmpty {
                        Image(systemName: "person.3.fill")
                            .font(.system(size: 11, weight: .semibold))
                        Text(groupName)
                            .font(.system(size: 13, weight: .semibold))
                            .lineLimit(1)
                    } else {
                        Image(systemName: "person.fill")
                            .font(.system(size: 11, weight: .semibold))
                        Text(lesson.teacherName)
                            .font(.system(size: 13, weight: .semibold))
                            .lineLimit(1)
                    }
                }
                .foregroundColor(.white.opacity(0.95))
                .padding(.horizontal, 12)
                .padding(.vertical, 6)
                .background(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.15),
                            Color.white.opacity(0.08)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(12)
                .overlay(
                    RoundedRectangle(cornerRadius: 12)
                        .stroke(
                            LinearGradient(
                                colors: [
                                    Color.white.opacity(0.25),
                                    Color.white.opacity(0.1)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 1
                        )
                )
                
                Spacer(minLength: 0)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(
            LinearGradient(
                colors: [
                    Color.white.opacity(0.12),
                    Color.white.opacity(0.06)
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .cornerRadius(18)
        .overlay(
            RoundedRectangle(cornerRadius: 18)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.25),
                            Color.white.opacity(0.08)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1.5
                )
        )
        .shadow(color: Color.black.opacity(0.2), radius: 12, x: 0, y: 4)
    }
}

struct EmptyScheduleView: View {
    var body: some View {
        VStack(spacing: 20) {
            Image(systemName: "moon.zzz.fill")
                .font(.system(size: 56))
                .foregroundStyle(
                    LinearGradient(
                        colors: [
                            Color(red: 0.376, green: 0.510, blue: 0.980),
                            Color(red: 0.231, green: 0.510, blue: 0.965)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .frame(width: 80, height: 80)
                .background(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.2),
                            Color.white.opacity(0.1)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
                .cornerRadius(20)
                .overlay(
                    RoundedRectangle(cornerRadius: 20)
                        .stroke(
                            LinearGradient(
                                colors: [
                                    Color.white.opacity(0.3),
                                    Color.white.opacity(0.1)
                                ],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            ),
                            lineWidth: 1.5
                        )
                )
                .shadow(color: Color(red: 0.376, green: 0.510, blue: 0.980).opacity(0.3), radius: 12, x: 0, y: 6)
            
            VStack(spacing: 8) {
                Text("Пар нет")
                    .font(.system(size: 22, weight: .bold))
                    .foregroundColor(.white)
                
                Text("Отдохните или выберите другой день")
                    .font(.system(size: 15, weight: .medium))
                    .foregroundColor(.white.opacity(0.7))
                    .multilineTextAlignment(.center)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 60)
        .padding(.horizontal, 20)
        .background(
            LinearGradient(
                colors: [
                    Color.white.opacity(0.1),
                    Color.white.opacity(0.05)
                ],
                startPoint: .topLeading,
                endPoint: .bottomTrailing
            )
        )
        .cornerRadius(24)
        .overlay(
            RoundedRectangle(cornerRadius: 24)
                .stroke(
                    LinearGradient(
                        colors: [
                            Color.white.opacity(0.2),
                            Color.white.opacity(0.08)
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    ),
                    lineWidth: 1.5
                )
        )
    }
}

